<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportMappingProfileResource;
use App\Models\ImportMappingProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportMappingProfileIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = ImportMappingProfile::query()->latest();

        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        return ImportMappingProfileResource::collection($query->get())->response();
    }
}
