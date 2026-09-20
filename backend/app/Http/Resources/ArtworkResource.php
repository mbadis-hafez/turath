<?php

namespace App\Http\Resources;

use App\Models\Artwork;
use Illuminate\Http\Request;

class ArtworkResource extends ArtworkListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Artwork $artwork */
        $artwork = $this->resource;

        return array_merge(parent::toArray($request), [
            'frame_dimensions' => [
                'height_cm' => $artwork->frame_height_cm,
                'width_cm' => $artwork->frame_width_cm,
                'depth_cm' => $artwork->frame_depth_cm,
                'raw' => $artwork->frame_dimensions_raw,
            ],
            'weight_kg' => $artwork->weight_kg,
            'signed' => $artwork->signed,
            'edition' => [
                'number' => $artwork->edition_number,
                'size' => $artwork->edition_size,
            ],
            'holder' => $artwork->holder ? new HolderResource($artwork->holder) : null,
            'holder_inventory_no' => $artwork->holder_inventory_no,
            'notes' => [
                'ar' => $artwork->notes_ar,
                'en' => $artwork->notes_en,
            ],
            'publication_status' => $artwork->publication_status,
            'created_at' => $artwork->created_at?->toIso8601String(),
            'updated_at' => $artwork->updated_at?->toIso8601String(),
        ]);
    }
}
