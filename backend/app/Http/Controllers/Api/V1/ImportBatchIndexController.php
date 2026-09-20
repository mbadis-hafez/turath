<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = ImportBatch::query()->with('uploadedBy')->latest();

        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->input('per_page', 24), 100);

        return ImportBatchResource::collection($query->paginate($perPage))->response();
    }
}
