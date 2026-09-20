<?php

namespace App\Support;

use App\Enums\HolderType;
use App\Models\Holder;

/**
 * Computes the public-facing display name for a holder. Private holders
 * (is_public_name = false) never expose their real name — only their type
 * and city, so the real name/is_public_name columns stay the single source
 * of truth and can never drift from what's shown.
 */
class HolderDisplayResolver
{
    /**
     * @return array{ar: string, en: string}
     */
    public static function resolve(Holder $holder): array
    {
        if ($holder->is_public_name) {
            return [
                'ar' => $holder->name_ar ?? '',
                'en' => $holder->name_en ?? '',
            ];
        }

        if ($holder->type === HolderType::Family->value && $holder->is_estate) {
            return ['ar' => 'ورثة الفنان', 'en' => "Artist's estate"];
        }

        if ($holder->type === HolderType::Family->value) {
            return ['ar' => 'مجموعة عائلية', 'en' => 'Family collection'];
        }

        if ($holder->city_ar || $holder->city_en) {
            return [
                'ar' => 'مجموعة خاصة، '.($holder->city_ar ?? $holder->city_en),
                'en' => 'Private collection, '.($holder->city_en ?? $holder->city_ar),
            ];
        }

        return ['ar' => 'مجموعة خاصة', 'en' => 'Private collection'];
    }
}
