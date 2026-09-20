<?php

namespace App\Http\Resources;

use App\Http\Controllers\Api\V1\ArtworkImageController;
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
            'image_url' => $this->publicImageUrl($artwork),
            'publication_status' => $artwork->publication_status,
            'created_at' => $artwork->created_at?->toIso8601String(),
            'updated_at' => $artwork->updated_at?->toIso8601String(),
        ]);
    }

    private function publicImageUrl(Artwork $artwork): ?string
    {
        if ($artwork->publication_status !== 'published') {
            return null;
        }
        $image = ArtworkImageController::primary($artwork);

        return $image !== null && $image->isClearForPublic() ? ArtworkImageController::urlFor($image) : null;
    }
}
