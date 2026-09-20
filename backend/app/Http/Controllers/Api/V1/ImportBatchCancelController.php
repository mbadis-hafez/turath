<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportBatchStatus;
use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;

class ImportBatchCancelController
{
    public function __invoke(ImportBatch $importBatch): JsonResponse
    {
        $importBatch->status = ImportBatchStatus::Cancelled->value;
        $importBatch->save();

        return response()->json(['data' => new ImportBatchResource($importBatch)]);
    }
}
