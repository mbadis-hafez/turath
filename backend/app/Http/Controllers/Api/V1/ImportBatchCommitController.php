<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ImportBatchResource;
use App\Models\ImportBatch;
use App\Support\Import\ImportCommitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ImportBatchCommitController
{
    public function __invoke(ImportBatch $importBatch): JsonResponse
    {
        $committer = new ImportCommitter;

        if (! $committer->isReadyToCommit($importBatch)) {
            throw ValidationException::withMessages([
                'rows' => ['Every row must be resolved (create_new / link_existing / skip) and free of unresolved validation errors before committing.'],
            ]);
        }

        $committer->commit($importBatch);
        $importBatch->refresh()->load(['uploadedBy', 'rows']);

        return response()->json(['data' => new ImportBatchResource($importBatch)]);
    }
}
