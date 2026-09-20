<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportBatchRowResource;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchRowIndexController
{
    public function __invoke(Request $request, ImportBatch $importBatch): JsonResponse
    {
        $query = $importBatch->rows()->orderBy('row_number');

        if ($matchStatus = $request->input('match_status')) {
            $query->where('match_status', $matchStatus);
        }

        if ($resolution = $request->input('resolution')) {
            $query->where('resolution', $resolution);
        }

        $perPage = min((int) $request->input('per_page', 50), 200);

        return ImportBatchRowResource::collection($query->paginate($perPage))->response();
    }
}
