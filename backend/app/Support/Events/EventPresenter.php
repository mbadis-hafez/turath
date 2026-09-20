<?php

namespace App\Support\Events;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\EventParticipant;
use App\ValueObjects\PartialDate;

class EventPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function date(mixed $date): ?array
    {
        return $date instanceof PartialDate && ! $date->isEmpty() ? $date->toArray() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Event $event): array
    {
        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'title' => ['ar' => $event->title_ar, 'en' => $event->title_en],
            'start' => self::date($event->getAttribute('start')),
            'end' => self::date($event->getAttribute('end')),
            'venue_name' => $event->venue_name,
            'city' => $event->city,
            'publication_status' => $event->publication_status,
        ];
    }

    /**
     * A participant row with its artist or artwork, or null when that record is gone or, for public
     * viewers, not itself published.
     *
     * @return array<string, mixed>|null
     */
    public static function participant(EventParticipant $row, bool $manage): ?array
    {
        $entity = $row->participant;

        if ($entity instanceof Artist) {
            if (! $manage && ($entity->publication_status !== 'published' || $entity->merged_into_id !== null)) {
                return null;
            }
            $shape = ['kind' => 'artist', 'entity' => ['id' => $entity->id, 'slug' => $entity->slug, 'name' => ['ar' => $entity->name_ar, 'en' => $entity->name_en]]];
        } elseif ($entity instanceof Artwork) {
            if (! $manage && $entity->publication_status !== 'published') {
                return null;
            }
            $shape = ['kind' => 'artwork', 'entity' => ['id' => $entity->id, 'title' => ['ar' => $entity->title_ar, 'en' => $entity->title_en]]];
        } else {
            return null;
        }

        return ['id' => $row->id, 'role' => $row->role, 'note' => $row->note, ...$shape];
    }

    /**
     * The published events an artist or artwork took part in, for their detail pages.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function eventsFor(Artist|Artwork $entity): array
    {
        return EventParticipant::query()
            ->where('participant_type', $entity::class)->where('participant_id', $entity->getKey())
            ->whereHas('event', fn ($q) => $q->where('publication_status', 'published'))
            ->with('event')->get()
            ->sortBy(fn (EventParticipant $r) => $r->event->start_year_from ?? PHP_INT_MAX)
            ->map(fn (EventParticipant $r) => [...self::summary($r->event), 'role' => $r->role, 'note' => $r->note])
            ->values()->all();
    }
}
