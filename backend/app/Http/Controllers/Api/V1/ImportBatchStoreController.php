<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Import\StoreImportBatchRequest;
use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use App\Models\ImportMappingProfile;
use App\Support\Import\ImportValidator;
use Illuminate\Http\JsonResponse;

class ImportBatchStoreController
{
    public function __invoke(StoreImportBatchRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $diskPath = $file->store('imports');

        $columnMap = $request->input('column_map');

        if ($columnMap === null && $request->filled('mapping_profile_id')) {
            $profile = ImportMappingProfile::find($request->input('mapping_profile_id'));
            $columnMap = $profile === null ? [] : $profile->column_map;
        }

        $batch = ImportBatch::create([
            'entity_type' => $request->input('entity_type'),
            'mapping_profile_id' => $request->input('mapping_profile_id'),
            'column_map' => $columnMap ?? [],
            'original_filename' => $file->getClientOriginalName(),
            'disk_path' => $diskPath,
            'status' => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        // Small-batch happy path: validate inline for a snappy first-time
        // experience. Batches over ~200 rows moving to a queued job is
        // deferred to a follow-up pass alongside the Holder/ArchiveItem
        // importers.
        (new ImportValidator)->validate($batch);
        $batch->refresh();

        return response()->json(['data' => new ImportBatchResource($batch)], 201);
    }
}
