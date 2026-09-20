<?php

namespace App\Support\Import;

use App\Enums\ImportMatchConfidence;
use App\Enums\ImportRowMatchStatus;
use App\Models\Artist;
use App\Support\ArabicNormalizer;

/**
 * D33: match first by artist_code if present and exact; otherwise suggest
 * candidates via normalized-name comparison, but never auto-merge — every
 * unmatched row still needs an explicit human resolution.
 */
class ArtistMatchResolver
{
    /**
     * @param  array<string, mixed>  $mapped
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    public function resolve(array $mapped): array
    {
        $code = $mapped['legacy_code'] ?? null;

        if (is_string($code) && $code !== '') {
            $artist = Artist::where('legacy_code', $code)->first();

            if ($artist !== null) {
                return $this->result(ImportRowMatchStatus::MatchedExact, $artist->id);
            }
        }

        $nameAr = $mapped['name_ar'] ?? null;
        $nameEn = $mapped['name_en'] ?? null;

        if (! is_string($nameAr) && ! is_string($nameEn)) {
            return $this->result(ImportRowMatchStatus::New);
        }

        $compactAr = is_string($nameAr) ? ArabicNormalizer::compact($nameAr) : null;
        $compactEn = is_string($nameEn) ? ArabicNormalizer::compact($nameEn) : null;

        $candidates = Artist::query()
            ->where(function ($q) use ($compactAr, $compactEn) {
                if ($compactAr) {
                    $q->orWhere('search_compact', 'like', '%'.$compactAr.'%');
                }
                if ($compactEn) {
                    $q->orWhere('search_compact', 'like', '%'.$compactEn.'%');
                }
            })
            ->get();

        if ($candidates->count() > 1) {
            return $this->result(ImportRowMatchStatus::Ambiguous, null, ImportMatchConfidence::Low);
        }

        if ($candidates->count() === 1) {
            $candidate = $candidates->first();

            $isExact = ($compactAr && $candidate->name_ar && ArabicNormalizer::compact($candidate->name_ar) === $compactAr)
                || ($compactEn && $candidate->name_en && ArabicNormalizer::compact($candidate->name_en) === $compactEn);

            return $this->result(
                ImportRowMatchStatus::MatchedSuggested,
                $candidate->id,
                $isExact ? ImportMatchConfidence::High : ImportMatchConfidence::Medium,
            );
        }

        return $this->result(ImportRowMatchStatus::New);
    }

    /**
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    private function result(ImportRowMatchStatus $status, ?int $entityId = null, ?ImportMatchConfidence $confidence = null): array
    {
        return [
            'status' => $status->value,
            'matched_entity_id' => $entityId,
            'match_confidence' => $confidence?->value,
        ];
    }
}
