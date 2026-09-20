<?php

namespace App\Support\Import;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;

interface Importer
{
    /**
     * Applies parsers to the mapped row, validates required fields (the
     * same rules the entity's own create-API request enforces — no
     * separate, looser importer-only rule set), and runs the entity's
     * MatchResolver. Never writes to the database.
     *
     * @param  array<string, mixed>  $mapped
     * @return array{
     *     mapped_data: array<string, mixed>,
     *     match_status: string,
     *     matched_entity_id: int|null,
     *     match_confidence: string|null,
     *     validation_errors: array<int, array{field: string, message: string}>,
     * }
     */
    public function validateRow(array $mapped): array;

    /**
     * Creates/updates/skips the real row per the human-set resolution.
     * Runs inside the caller's transaction; must not itself decide
     * publication/access state beyond each entity's own safe default
     * (D31: imports never auto-publish).
     *
     * @return array{commit_result: string, resulting_entity_id: int|null}
     */
    public function commit(ImportBatchRow $row, ImportBatch $batch): array;
}
