<?php

namespace App\Models\Observers;

use App\Models\Event;
use App\Support\ArabicNormalizer;
use App\Support\Completeness\RecomputesCompleteness;
use App\Support\Proposals\RecordsRevisions;
use Illuminate\Support\Facades\DB;

class EventObserver
{
    use RecomputesCompleteness, RecordsRevisions;

    private const SEARCH_AFFECTING_COLUMNS = ['title_ar', 'title_en', 'venue_name', 'city', 'description_ar', 'description_en'];

    public function creating(Event $event): void
    {
        $this->assignCreator($event);
    }

    public function saved(Event $event): void
    {
        // Before anything else: the search-text rebuild calls syncOriginal(), which wipes the old values.
        $this->recordDirectEditRevision($event);

        if ($event->wasRecentlyCreated || $event->wasChanged(self::SEARCH_AFFECTING_COLUMNS)) {
            $text = ArabicNormalizer::normalize(implode(' ', array_filter([
                $event->title_ar, $event->title_en, $event->venue_name, $event->city, $event->description_ar, $event->description_en,
            ], fn ($p) => is_string($p) && $p !== '')));
            DB::table($event->getTable())->where('id', $event->getKey())->update(['search_text' => $text]);
            $event->forceFill(['search_text' => $text])->syncOriginal();
        }

        $this->recomputeCompleteness($event);
    }
}
