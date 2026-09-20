<?php

namespace App\Support\Import;

use App\Enums\ImportRowMatchStatus;
use App\Models\Holder;
use App\Support\ArabicNormalizer;

/**
 * D34: match by legacy_code, or otherwise by exact normalized name only —
 * no fuzzy suggestion. Institution names are short enough that fuzzy
 * matching them is riskier than for people's names (F1's artist matching).
 * Anything that isn't an exact match is treated as "create new holder" by
 * default, reviewable before commit like every other row.
 */
class HolderMatchResolver
{
    /**
     * @param  array<string, mixed>  $mapped
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    public function resolve(array $mapped): array
    {
        $code = $mapped['legacy_code'] ?? null;

        if (is_string($code) && $code !== '') {
            $holder = Holder::where('legacy_code', $code)->first();

            if ($holder !== null) {
                return $this->result(ImportRowMatchStatus::MatchedExact, $holder->id);
            }
        }

        $nameAr = $mapped['name_ar'] ?? null;
        $nameEn = $mapped['name_en'] ?? null;

        if (! is_string($nameAr) && ! is_string($nameEn)) {
            return $this->result(ImportRowMatchStatus::New);
        }

        $compactAr = is_string($nameAr) ? ArabicNormalizer::compact($nameAr) : null;
        $compactEn = is_string($nameEn) ? ArabicNormalizer::compact($nameEn) : null;

        $matches = Holder::all()->filter(function (Holder $holder) use ($compactAr, $compactEn) {
            $holderCompactAr = $holder->name_ar !== null ? ArabicNormalizer::compact($holder->name_ar) : null;
            $holderCompactEn = $holder->name_en !== null ? ArabicNormalizer::compact($holder->name_en) : null;

            return ($compactAr !== null && $compactAr === $holderCompactAr)
                || ($compactEn !== null && $compactEn === $holderCompactEn);
        });

        if ($matches->count() > 1) {
            return $this->result(ImportRowMatchStatus::Ambiguous);
        }

        if ($matches->count() === 1) {
            return $this->result(ImportRowMatchStatus::MatchedExact, $matches->first()->id);
        }

        return $this->result(ImportRowMatchStatus::New);
    }

    /**
     * @return array{status: string, matched_entity_id: int|null, match_confidence: string|null}
     */
    private function result(ImportRowMatchStatus $status, ?int $entityId = null): array
    {
        return [
            'status' => $status->value,
            'matched_entity_id' => $entityId,
            'match_confidence' => null,
        ];
    }
}
