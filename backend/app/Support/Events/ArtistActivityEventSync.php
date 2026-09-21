<?php

namespace App\Support\Events;

use App\Enums\EventParticipantRole;
use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Models\Event;
use App\Support\Completeness\CompletenessCalculator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps an artist's exhibition, talk and symposium lines mirrored into the events
 * registry: each line owns at most one auto-created draft event (tracked by
 * artist_entries.event_id), with the artist attached as a participant. Awards and
 * educations stay profile-only. The artist line is the source of truth for the
 * event's title, venue and year; staff can enrich and publish the event itself.
 */
class ArtistActivityEventSync
{
    private const MIRRORED_TYPES = ['exhibition', 'talk', 'symposium'];

    /**
     * @param  Collection<int, ArtistEntry>  $before  activity rows as they were before the entries sync
     */
    public static function reconcile(Artist $artist, Collection $before): void
    {
        $after = $artist->entries()->whereIn('type', self::MIRRORED_TYPES)->get();
        $keptIds = $after->modelKeys();

        foreach ($before as $entry) {
            if (in_array($entry->id, $keptIds, true) || $entry->event_id === null) {
                continue;
            }
            // The line is gone: remove its auto-created draft. A published event
            // has a life of its own in the registry and is kept.
            $event = Event::find($entry->event_id);
            if ($event !== null && $event->publication_status === 'draft') {
                $event->delete();
            }
        }

        foreach ($after as $entry) {
            /** @var ArtistEntry $entry */
            $attributes = [
                'event_type' => $entry->type,
                'title_ar' => $entry->title_ar,
                'title_en' => $entry->title_en,
                'venue_name' => $entry->place_en ?? $entry->place_ar,
                'start_year_from' => $entry->year_from,
                'start_year_to' => $entry->year_to,
            ];

            $event = $entry->event_id !== null ? Event::find($entry->event_id) : null;

            if ($event !== null) {
                $event->fill($attributes);
                if ($event->isDirty()) {
                    $event->save();
                }

                continue;
            }

            $event = Event::create($attributes);
            $event->participants()->create([
                'participant_type' => Artist::class,
                'participant_id' => $artist->id,
                'role' => EventParticipantRole::Participant->value,
            ]);
            (new CompletenessCalculator)->recompute($event);

            $entry->update(['event_id' => $event->id]);
        }
    }
}
