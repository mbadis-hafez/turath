<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Import\UpdateImportBatchRowRequest;
use App\Http\Resources\ImportBatchRowResource;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use Illuminate\Http\JsonResponse;

class ImportBatchRowUpdateController
{
    public function __invoke(UpdateImportBatchRowRequest $request, ImportBatch $importBatch, ImportBatchRow $row): JsonResponse
    {
        abort_if($row->import_batch_id !== $importBatch->id, 404);

        if ($request->has('resolution')) {
            $row->resolution = $request->input('resolution');
            $row->resolved_by_user_id = $request->user()->id;
            $row->resolved_at = now();
        }

        if ($request->has('matched_entity_id')) {
            $row->matched_entity_id = $request->input('matched_entity_id');
        }

        if ($request->has('mapped_data')) {
            $row->mapped_data = array_merge($row->mapped_data ?? [], $request->input('mapped_data'));
        }

        $row->save();

        return response()->json(['data' => new ImportBatchRowResource($row)]);
    }
}
