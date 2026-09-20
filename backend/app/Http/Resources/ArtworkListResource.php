<?php

namespace App\Http\Resources;

use App\Models\Artwork;
use App\ValueObjects\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtworkListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Artwork $artwork */
        $artwork = $this->resource;

        return [
            'id' => $artwork->id,
            'legacy_ref' => $artwork->legacy_ref,
            'title' => [
                'ar' => $artwork->title_ar,
                'en' => $artwork->title_en,
            ],
            'is_untitled' => $artwork->is_untitled,
            'artist' => $artwork->artist ? [
                'id' => $artwork->artist->id,
                'slug' => $artwork->artist->slug,
                'name' => [
                    'ar' => $artwork->artist->name_ar,
                    'en' => $artwork->artist->name_en,
                ],
            ] : null,
            'attribution_certainty' => $artwork->attribution_certainty,
            'category' => $artwork->category,
            'medium' => [
                'ar' => $artwork->medium_ar,
                'en' => $artwork->medium_en,
            ],
            'creation' => self::date($artwork->creation),
            'dimensions' => [
                'height_cm' => $artwork->height_cm,
                'width_cm' => $artwork->width_cm,
                'depth_cm' => $artwork->depth_cm,
                'raw' => $artwork->dimensions_raw,
            ],
        ];
    }

    /**
     * @return array{display: string|null, year_from: int|null, year_to: int|null, calendar: string|null, certainty: string|null}|null
     */
    protected static function date(?PartialDate $date): ?array
    {
        if ($date === null) {
            return null;
        }

        return [
            'display' => $date->display,
            'year_from' => $date->yearFrom,
            'year_to' => $date->yearTo,
            'calendar' => $date->calendar?->value,
            'certainty' => $date->certainty?->value,
        ];
    }
}
