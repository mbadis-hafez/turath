<?php

namespace App\Http\Resources;

use App\Enums\NameVariantType;
use App\Http\Controllers\Api\V1\ArtistEntriesSyncController;
use App\Http\Controllers\Api\V1\ArtistPortraitController;
use App\Http\Controllers\Api\V1\ArtistSocialLinksSyncController;
use App\Models\Artist;
use App\Support\Events\EventPresenter;
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

        $data = array_merge(parent::toArray($request), [
            'legacy_code' => $artist->legacy_code,
            'events' => EventPresenter::eventsFor($artist),
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
            'nationality' => ['ar' => $artist->nationality_ar, 'en' => $artist->nationality_en],
            'classification' => ['ar' => $artist->classification_ar, 'en' => $artist->classification_en],
            ...ArtistEntriesSyncController::present($artist),
            'social_links' => ArtistSocialLinksSyncController::present($artist, publicOnly: true),
            'portrait_url' => ArtistPortraitController::isPublic($artist) ? "/api/v1/artists/{$artist->id}/portrait" : null,
            'verified_at' => $artist->verified_at?->toIso8601String(),
            'publication_status' => $artist->publication_status,
            'created_at' => $artist->created_at?->toIso8601String(),
            'updated_at' => $artist->updated_at?->toIso8601String(),
        ]);

        // Verifier details are manager-only; safe for guests.
        if ($request->user()?->can('artists.manage') ?? false) {
            $data['verified_by'] = $artist->verifiedBy ? [
                'id' => $artist->verifiedBy->id,
                'name' => $artist->verifiedBy->name,
            ] : null;
        }

        return $data;
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
