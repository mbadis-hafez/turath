<?php

namespace App\Support\Import;

use App\Enums\HolderType;
use App\Enums\ImportRowCommitResult;
use App\Enums\ImportRowResolution;
use App\Models\Holder;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;

class HolderImporter implements Importer
{
    public function __construct(private readonly HolderMatchResolver $matcher = new HolderMatchResolver) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @return array{mapped_data: array<string, mixed>, match_status: string, matched_entity_id: int|null, match_confidence: string|null, validation_errors: array<int, array{field: string, message: string}>}
     */
    public function validateRow(array $mapped): array
    {
        $errors = [];

        $type = $this->str($mapped['type'] ?? null);
        $validTypes = array_map(fn (HolderType $t) => $t->value, HolderType::cases());

        if ($type === null || ! in_array($type, $validTypes, true)) {
            $errors[] = ['field' => 'type', 'message' => 'A valid holder type is required.'];
        }

        $nameAr = $this->str($mapped['name_ar'] ?? null);
        $nameEn = $this->str($mapped['name_en'] ?? null);

        if ($nameAr === null && $nameEn === null) {
            $errors[] = ['field' => 'name_ar', 'message' => 'At least one of name_ar or name_en is required.'];
        }

        $isPublicNameRaw = $mapped['is_public_name'] ?? null;
        $isPublicName = $isPublicNameRaw !== null
            ? filter_var($isPublicNameRaw, FILTER_VALIDATE_BOOLEAN)
            : in_array($type, [HolderType::Institution->value, HolderType::ArtistEstate->value], true);

        $mappedData = [
            'legacy_code' => $this->str($mapped['legacy_code'] ?? null),
            'type' => $type,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'city_ar' => $this->str($mapped['city_ar'] ?? null),
            'city_en' => $this->str($mapped['city_en'] ?? null),
            'country_ar' => $this->str($mapped['country_ar'] ?? null),
            'country_en' => $this->str($mapped['country_en'] ?? null),
            'is_public_name' => $isPublicName,
            'is_estate' => isset($mapped['is_estate']) ? filter_var($mapped['is_estate'], FILTER_VALIDATE_BOOLEAN) : null,
            'internal_notes' => $this->str($mapped['internal_notes'] ?? null),
        ];

        $match = $errors === []
            ? $this->matcher->resolve($mappedData)
            : ['status' => 'error', 'matched_entity_id' => null, 'match_confidence' => null];

        return [
            'mapped_data' => $mappedData,
            'match_status' => $match['status'],
            'matched_entity_id' => $match['matched_entity_id'],
            'match_confidence' => $match['match_confidence'],
            'validation_errors' => $errors,
        ];
    }

    public function commit(ImportBatchRow $row, ImportBatch $batch): array
    {
        $data = $row->mapped_data ?? [];
        $summary = "Imported via batch {$batch->id}, row {$row->row_number}";

        if ($row->resolution === ImportRowResolution::CreateNew->value) {
            request()->merge(['edit_summary' => $summary]);

            $holder = Holder::create($this->attributes($data));

            return ['commit_result' => ImportRowCommitResult::Created->value, 'resulting_entity_id' => $holder->id];
        }

        if ($row->resolution === ImportRowResolution::LinkExisting->value) {
            $holder = Holder::find($row->matched_entity_id);

            if ($holder === null) {
                return ['commit_result' => ImportRowCommitResult::Failed->value, 'resulting_entity_id' => null];
            }

            request()->merge(['edit_summary' => $summary]);
            $holder->fill(array_filter($this->attributes($data), fn ($v) => $v !== null));
            $holder->save();

            return ['commit_result' => ImportRowCommitResult::Updated->value, 'resulting_entity_id' => $holder->id];
        }

        return ['commit_result' => ImportRowCommitResult::Skipped->value, 'resulting_entity_id' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'legacy_code' => $data['legacy_code'] ?? null,
            'type' => $data['type'] ?? null,
            'name_ar' => $data['name_ar'] ?? null,
            'name_en' => $data['name_en'] ?? null,
            'city_ar' => $data['city_ar'] ?? null,
            'city_en' => $data['city_en'] ?? null,
            'country_ar' => $data['country_ar'] ?? null,
            'country_en' => $data['country_en'] ?? null,
            'is_public_name' => $data['is_public_name'] ?? false,
            'is_estate' => $data['is_estate'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
        ];
    }

    private function str(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
