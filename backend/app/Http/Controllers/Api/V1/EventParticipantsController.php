<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventParticipantRole;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Curation\ChildSync;
use App\Support\Events\EventPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Replaces an event's participant list with the one sent, diff-aware so unchanged rows stay unchanged in the audit log. */
class EventParticipantsController
{
    public function __invoke(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'participants' => ['required', 'array', 'max:500'],
            'participants.*.id' => ['nullable', 'integer'],
            'participants.*.type' => ['required', Rule::in(['artist', 'artwork'])],
            'participants.*.participant_id' => ['required', 'integer'],
            'participants.*.role' => ['required', Rule::enum(EventParticipantRole::class)],
            'participants.*.note' => ['nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $items = [];
        foreach ($data['participants'] as $i => $p) {
            $class = $p['type'] === 'artist' ? Artist::class : Artwork::class;
            if (! $class::query()->whereKey($p['participant_id'])->exists()) {
                throw ValidationException::withMessages(["participants.{$i}.participant_id" => ['The selected record does not exist.']]);
            }
            $items[] = [
                'id' => $p['id'] ?? null, 'participant_type' => $class, 'participant_id' => $p['participant_id'],
                'role' => $p['role'], 'note' => $p['note'] ?? null,
            ];
        }
        if (count(array_unique(array_map(fn ($i) => "{$i['participant_type']}:{$i['participant_id']}:{$i['role']}", $items))) !== count($items)) {
            throw ValidationException::withMessages(['participants' => ['The same participant cannot have the same role twice.']]);
        }

        ChildSync::sync($event->participants(), $items);
        $event->touch();
        (new CompletenessCalculator)->recompute($event);

        return response()->json(['data' => $event->participants()->with('participant')->get()
            ->map(fn ($p) => EventPresenter::participant($p, true))->filter()->values()]);
    }
}
