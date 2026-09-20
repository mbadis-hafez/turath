<?php

namespace App\Support\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowCommitResult;
use App\Enums\ImportRowResolution;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportCommitter
{
    public function __construct(private readonly ImportValidator $validator = new ImportValidator) {}

    /**
     * Re-validates (idempotently, in case rows were edited since the last
     * validate) then commits every non-pending, non-error row. Each row
     * commits in its own transaction so one bad row can't roll back an
     * otherwise-good batch.
     */
    public function commit(ImportBatch $batch): void
    {
        $this->validator->validate($batch);
        $batch->refresh();

        $importer = $this->validator->importerFor($batch->entity_type);

        foreach ($batch->rows()->get() as $row) {
            if ($row->resolution === ImportRowResolution::Skip->value) {
                $row->update(['commit_result' => ImportRowCommitResult::Skipped->value]);

                continue;
            }

            try {
                $result = DB::transaction(fn () => $importer->commit($row, $batch));
                $row->update($result);
            } catch (Throwable) {
                $row->update(['commit_result' => ImportRowCommitResult::Failed->value]);
            }
        }

        $batch->status = ImportBatchStatus::Committed->value;
        $batch->committed_at = now();
        $batch->save();
    }

    /**
     * Whether the batch can be committed: every row must have an explicit
     * resolution and no unresolved validation error.
     */
    public function isReadyToCommit(ImportBatch $batch): bool
    {
        return ! $batch->rows()
            ->where(function ($q) {
                $q->where('resolution', ImportRowResolution::Pending->value)
                    ->orWhere(function ($and) {
                        $and->where('resolution', '!=', ImportRowResolution::Skip->value)
                            ->whereNotNull('validation_errors')
                            ->whereRaw('JSON_LENGTH(validation_errors) > 0');
                    });
            })
            ->exists();
    }
}
