<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use App\Support\Import\ImportValidator;
use Illuminate\Http\JsonResponse;

class ImportBatchRevalidateController
{
    public function __invoke(ImportBatch $importBatch): JsonResponse
    {
        (new ImportValidator)->validate($importBatch);
        $importBatch->refresh();

        return response()->json(['data' => new ImportBatchResource($importBatch)]);
    }
}
