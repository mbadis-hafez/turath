<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Event\SyncEventParticipantsRequest;
use App\Models\Event;
use App\Support\Curation\EventSections;
use App\Support\Events\EventPresenter;
use Illuminate\Http\JsonResponse;

/** Replaces an event's participant list with the one sent, diff-aware so unchanged rows stay unchanged in the audit log. */
class EventParticipantsController
{
    public function __invoke(SyncEventParticipantsRequest $request, Event $event): JsonResponse
    {
        (new EventSections)->syncParticipants($event, $request->input('participants', []));

        return response()->json(['data' => $event->participants()->with('participant')->get()
            ->map(fn ($p) => EventPresenter::participant($p, true))->filter()->values()]);
    }
}
