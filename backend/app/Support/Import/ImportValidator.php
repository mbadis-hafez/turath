<?php

namespace App\Support\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowMatchStatus;
use App\Enums\ImportRowResolution;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Orchestrates parser + column mapping + per-entity Importer across every
 * row of a batch, producing the aggregate counts on ImportBatch and the
 * per-row match_status/validation_errors/mapped_data. Runs at "validate"
 * time and again, idempotently, at "commit" time and via /revalidate.
 */
class ImportValidator
{
    /**
     * @var array<string, class-string<Importer>>
     */
    private const IMPORTERS = [
        'artist' => ArtistImporter::class,
        'artwork' => ArtworkImporter::class,
        'holder' => HolderImporter::class,
        'archive_item' => ArchiveItemImporter::class,
    ];

    public function validate(ImportBatch $batch): void
    {
        $importer = $this->importerFor($batch->entity_type);
        $columnMap = $batch->column_map ?? [];

        $path = Storage::disk('local')->path($batch->disk_path);
        $rawRows = SpreadsheetParser::parse($path);

        $newCount = 0;
        $matchedCount = 0;
        $errorCount = 0;

        foreach ($rawRows as $rowNumber => $rawRow) {
            $mapped = ColumnMapper::map($rawRow, $columnMap);
            $result = $importer->validateRow($mapped);

            $existing = $batch->rows()->where('row_number', $rowNumber)->first();

            $attributes = [
                'row_number' => $rowNumber,
                'raw_data' => $rawRow,
                'mapped_data' => $result['mapped_data'],
                'match_status' => $result['validation_errors'] !== [] ? ImportRowMatchStatus::Error->value : $result['match_status'],
                'matched_entity_id' => $result['matched_entity_id'],
                'match_confidence' => $result['match_confidence'],
                'validation_errors' => $result['validation_errors'],
            ];

            if ($existing !== null) {
                // Idempotent re-validation: never reset a human-set
                // resolution just because the row was re-parsed.
                $existing->fill($attributes);
                $existing->save();
            } else {
                $attributes['import_batch_id'] = $batch->id;
                $attributes['resolution'] = ImportRowResolution::Pending->value;
                ImportBatchRow::create($attributes);
            }

            if ($attributes['match_status'] === ImportRowMatchStatus::Error->value) {
                $errorCount++;
            } elseif ($attributes['match_status'] === ImportRowMatchStatus::New->value) {
                $newCount++;
            } else {
                $matchedCount++;
            }
        }

        $batch->row_count = count($rawRows);
        $batch->new_count = $newCount;
        $batch->matched_count = $matchedCount;
        $batch->error_count = $errorCount;
        $batch->skipped_count = $batch->rows()->where('resolution', ImportRowResolution::Skip->value)->count();
        $batch->status = ImportBatchStatus::Validated->value;
        $batch->validated_at = now();
        $batch->save();
    }

    public function importerFor(string $entityType): Importer
    {
        $class = self::IMPORTERS[$entityType] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No importer registered for entity type [{$entityType}].");
        }

        return new $class;
    }
}
