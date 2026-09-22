<?php

namespace App\Support\Curation;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Support\Completeness\CompletenessCalculator;
use App\Support\Proposals\RecordUpdater;
use Illuminate\Validation\ValidationException;

class EventSections
{
    /**
     * Flat field update, mirroring EventController::update.
     *
     * @param  array<string, mixed>  $mappedAttributes  column => new value
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function applyFields(Event $event, array $mappedAttributes): array
    {
        return RecordUpdater::apply($event, $mappedAttributes);
    }

    /**
     * Replaces an event's participant list, mirroring EventParticipantsController:
     * existence check per item, duplicate role check, diff-aware sync, touch and
     * completeness recompute.
     *
     * @param  array<int, array<string, mixed>>  $participants
     */
    public function syncParticipants(Event $event, array $participants): void
    {
        $items = [];
        foreach ($participants as $i => $p) {
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
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     */
    public function participantsDiffer(Event $event, array $participants): bool
    {
        $live = $event->participants()->get()
            ->map(fn (EventParticipant $p) => [
                'id' => $p->id, 'participant_type' => $p->participant_type, 'participant_id' => $p->participant_id,
                'role' => $p->role, 'note' => $p->note,
            ])->all();

        $incoming = array_map(fn (array $p) => [
            'id' => $p['id'] ?? null, 'participant_type' => $p['type'] === 'artist' ? Artist::class : Artwork::class,
            'participant_id' => $p['participant_id'], 'role' => $p['role'], 'note' => $p['note'] ?? null,
        ], $participants);

        return ChildDiffers::check($live, $incoming);
    }
}
