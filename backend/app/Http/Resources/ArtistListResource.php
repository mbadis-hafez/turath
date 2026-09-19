<?php

namespace App\Http\Resources;

use App\Models\Artist;
use App\ValueObjects\PartialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Artist $artist */
        $artist = $this->resource;

        return [
            'id' => $artist->id,
            'slug' => $artist->slug,
            'name' => [
                'ar' => $artist->name_ar,
                'en' => $artist->name_en,
            ],
            'birth' => self::date($artist->birth),
            'death' => self::date($artist->death),
            'living_status' => $artist->living_status,
            'verified_status' => $artist->verified_status,
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
