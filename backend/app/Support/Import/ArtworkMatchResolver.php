<?php

namespace App\Support\Import;

use App\Enums\ImportMatchConfidence;
use App\Enums\ImportRowMatchStatus;
use App\Models\Artwork;
use App\Support\ArabicNormalizer;
use App\ValueObjects\ParsedDimensions;

/**
 * D35: dedupe is a suggestion only, keyed on (artist_id + normalized title +
 * parsed dimensions). Every row still requires an explicit human resolution
 * before commit — this never auto-links.
 */
class ArtworkMatchResolver
{
    /**
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    public function resolve(?int $artistId, ?string $titleAr, ?string $titleEn, ?ParsedDimensions $dimensions): array
    {
        if ($artistId === null) {
            return $this->result(ImportRowMatchStatus::New);
        }

        $normalizedTitle = $this->normalizedTitle($titleAr, $titleEn);

        $best = null;
        $bestScore = 0;

        foreach (Artwork::where('artist_id', $artistId)->get() as $candidate) {
            $score = $this->score($candidate, $normalizedTitle, $dimensions);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if ($best === null || $bestScore === 0) {
            return $this->result(ImportRowMatchStatus::New);
        }

        $confidence = match (true) {
            $bestScore >= 4 => ImportMatchConfidence::High,
            $bestScore >= 2 => ImportMatchConfidence::Medium,
            default => ImportMatchConfidence::Low,
        };

        return $this->result(ImportRowMatchStatus::MatchedSuggested, $best->id, $confidence);
    }

    private function score(Artwork $candidate, ?string $normalizedTitle, ?ParsedDimensions $dimensions): int
    {
        $score = 0;

        $candidateTitle = $this->normalizedTitle($candidate->title_ar, $candidate->title_en);

        if ($normalizedTitle !== null && $candidateTitle !== null && $normalizedTitle === $candidateTitle) {
            $score += 2;
        }

        if ($dimensions?->heightCm !== null && $candidate->height_cm !== null
            && abs($dimensions->heightCm - (float) $candidate->height_cm) < 0.5) {
            $score += 1;
        }

        if ($dimensions?->widthCm !== null && $candidate->width_cm !== null
            && abs($dimensions->widthCm - (float) $candidate->width_cm) < 0.5) {
            $score += 1;
        }

        return $score;
    }

    private function normalizedTitle(?string $titleAr, ?string $titleEn): ?string
    {
        $title = $titleAr ?? $titleEn;

        return $title !== null ? ArabicNormalizer::compact($title) : null;
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
