<?php

namespace App\Support\Import;

use App\Enums\ImportRowCommitResult;
use App\Enums\ImportRowResolution;
use App\Models\Artist;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\ValueObjects\PartialDate;

class ArtistImporter implements Importer
{
    public function __construct(private readonly ArtistMatchResolver $matcher = new ArtistMatchResolver) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @return array{mapped_data: array<string, mixed>, match_status: string, matched_entity_id: int|null, match_confidence: string|null, validation_errors: array<int, array{field: string, message: string}>}
     */
    public function validateRow(array $mapped): array
    {
        $errors = [];

        $nameAr = $this->str($mapped['name_ar'] ?? null);
        $nameEn = $this->str($mapped['name_en'] ?? null);

        if ($nameAr === null && $nameEn === null) {
            $errors[] = ['field' => 'name_ar', 'message' => 'At least one of name_ar or name_en is required.'];
        }

        $mappedData = [
            'legacy_code' => $this->str($mapped['legacy_code'] ?? null),
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'bio_ar' => $this->str($mapped['bio_ar'] ?? null),
            'bio_en' => $this->str($mapped['bio_en'] ?? null),
            'birth' => $this->parseDate($mapped['birth'] ?? null),
            'birth_place_ar' => $this->str($mapped['birth_place_ar'] ?? null),
            'birth_place_en' => $this->str($mapped['birth_place_en'] ?? null),
            'death' => $this->parseDate($mapped['death'] ?? null),
            'death_place_ar' => $this->str($mapped['death_place_ar'] ?? null),
            'death_place_en' => $this->str($mapped['death_place_en'] ?? null),
            'living_status' => $this->str($mapped['living_status'] ?? null),
        ];

        $match = $this->matcher->resolve($mappedData);

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

            $artist = Artist::create($this->attributes($data));

            return ['commit_result' => ImportRowCommitResult::Created->value, 'resulting_entity_id' => $artist->id];
        }

        if ($row->resolution === ImportRowResolution::LinkExisting->value) {
            $artist = Artist::find($row->matched_entity_id);

            if ($artist === null) {
                return ['commit_result' => ImportRowCommitResult::Failed->value, 'resulting_entity_id' => null];
            }

            request()->merge(['edit_summary' => $summary]);
            $artist->fill(array_filter($this->attributes($data), fn ($v) => $v !== null));
            $artist->save();

            return ['commit_result' => ImportRowCommitResult::Updated->value, 'resulting_entity_id' => $artist->id];
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
            'name_ar' => $data['name_ar'] ?? null,
            'name_en' => $data['name_en'] ?? null,
            'bio_ar' => $data['bio_ar'] ?? null,
            'bio_en' => $data['bio_en'] ?? null,
            'birth' => PartialDate::fromArray($data['birth'] ?? null),
            'birth_place_ar' => $data['birth_place_ar'] ?? null,
            'birth_place_en' => $data['birth_place_en'] ?? null,
            'death' => PartialDate::fromArray($data['death'] ?? null),
            'death_place_ar' => $data['death_place_ar'] ?? null,
            'death_place_en' => $data['death_place_en'] ?? null,
            'living_status' => $data['living_status'] ?? 'unknown',
            // D31: imports never auto-publish, regardless of spreadsheet content.
            'publication_status' => 'draft',
            'verified_status' => 'unverified',
        ];
    }

    private function parseDate(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return PartialDate::fromString($value)?->toArray();
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
