<?php

namespace App\Http\Resources;

use App\Enums\NameVariantType;
use App\Models\Artist;
use Illuminate\Http\Request;

class ArtistResource extends ArtistListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Artist $artist */
        $artist = $this->resource;

        return array_merge(parent::toArray($request), [
            'legacy_code' => $artist->legacy_code,
            'bio' => [
                'ar' => $artist->bio_ar,
                'en' => $artist->bio_en,
            ],
            'birth' => self::dateWithPlaces($artist, 'birth'),
            'death' => self::dateWithPlaces($artist, 'death'),
            'also_known_as' => $artist->variants
                ->where('type', '!=', NameVariantType::Typo->value)
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'language' => $variant->language,
                    'type' => $variant->type,
                    'source_note' => $variant->source_note,
                ])
                ->values()
                ->all(),
            'verified_at' => $artist->verified_at?->toIso8601String(),
            'publication_status' => $artist->publication_status,
            'created_at' => $artist->created_at?->toIso8601String(),
            'updated_at' => $artist->updated_at?->toIso8601String(),
            $this->when($request->user()?->can('artists.manage') ?? false, [
                'verified_by' => $artist->verifiedBy ? [
                    'id' => $artist->verifiedBy->id,
                    'name' => $artist->verifiedBy->name,
                ] : null,
            ]),
        ]);
    }

    /**
     * @return array{display: string|null, year_from: int|null, year_to: int|null, calendar: string|null, certainty: string|null, place: array{ar: string|null, en: string|null}}|null
     */
    private static function dateWithPlaces(Artist $artist, string $prefix): ?array
    {
        $date = self::date($artist->{$prefix});

        if ($date === null) {
            return null;
        }

        return array_merge($date, [
            'place' => [
                'ar' => $artist->{"{$prefix}_place_ar"},
                'en' => $artist->{"{$prefix}_place_en"},
            ],
        ]);
    }
}
