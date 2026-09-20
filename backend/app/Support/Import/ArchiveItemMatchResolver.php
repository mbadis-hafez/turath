<?php

namespace App\Support\Import;

use App\Enums\ImportRowMatchStatus;
use App\Models\ArchiveItem;

/**
 * Archive items have no natural fuzzy-matching signal (unlike an artist's
 * name or an artwork's title+dimensions) — a scanned article or photo
 * doesn't have a stable "title" institutions agree on. Matching is exact
 * legacy_ref only; everything else is a new item, reviewable before commit
 * like every other row.
 */
class ArchiveItemMatchResolver
{
    /**
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    public function resolve(?string $legacyRef): array
    {
        if ($legacyRef === null) {
            return ['status' => ImportRowMatchStatus::New->value, 'matched_entity_id' => null, 'match_confidence' => null];
        }

        $item = ArchiveItem::where('legacy_ref', $legacyRef)->first();

        if ($item === null) {
            return ['status' => ImportRowMatchStatus::New->value, 'matched_entity_id' => null, 'match_confidence' => null];
        }

        return ['status' => ImportRowMatchStatus::MatchedExact->value, 'matched_entity_id' => $item->id, 'match_confidence' => null];
    }
}
