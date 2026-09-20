<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;

class ImportBatchShowController
{
    public function __invoke(ImportBatch $importBatch): JsonResponse
    {
        $importBatch->load('uploadedBy');

        return response()->json(['data' => new ImportBatchResource($importBatch)]);
    }
}
