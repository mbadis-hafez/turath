<?php

namespace App\Http\Resources;

use App\Models\ImportMappingProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportMappingProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportMappingProfile $profile */
        $profile = $this->resource;

        return [
            'id' => $profile->id,
            'entity_type' => $profile->entity_type,
            'name' => $profile->name,
            'column_map' => $profile->column_map,
            'created_at' => $profile->created_at?->toIso8601String(),
        ];
    }
}
