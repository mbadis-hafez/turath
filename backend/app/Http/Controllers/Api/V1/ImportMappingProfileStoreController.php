<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Import\StoreImportMappingProfileRequest;
use App\Http\Resources\ImportMappingProfileResource;
use App\Models\ImportMappingProfile;
use Illuminate\Http\JsonResponse;

class ImportMappingProfileStoreController
{
    public function __invoke(StoreImportMappingProfileRequest $request): JsonResponse
    {
        $profile = ImportMappingProfile::create([
            'entity_type' => $request->input('entity_type'),
            'name' => $request->input('name'),
            'column_map' => $request->input('column_map'),
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => new ImportMappingProfileResource($profile)], 201);
    }
}
