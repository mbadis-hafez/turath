<?php

namespace App\Support\Import;

use App\Enums\ArchiveItemType;
use App\Enums\ImportRowCommitResult;
use App\Enums\ImportRowResolution;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\ValueObjects\PartialDate;

class ArchiveItemImporter implements Importer
{
    public function __construct(private readonly ArchiveItemMatchResolver $matcher = new ArchiveItemMatchResolver) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @return array{mapped_data: array<string, mixed>, match_status: string, matched_entity_id: int|null, match_confidence: string|null, validation_errors: array<int, array{field: string, message: string}>}
     */
    public function validateRow(array $mapped): array
    {
        $errors = [];

        $itemType = $this->str($mapped['item_type'] ?? null);
        $validTypes = array_map(fn (ArchiveItemType $t) => $t->value, ArchiveItemType::cases());

        if ($itemType === null || ! in_array($itemType, $validTypes, true)) {
            $errors[] = ['field' => 'item_type', 'message' => 'A valid item_type is required.'];
        }

        $titleAr = $this->str($mapped['title_ar'] ?? null);
        $titleEn = $this->str($mapped['title_en'] ?? null);
        $descriptionAr = $this->str($mapped['description_ar'] ?? null);
        $descriptionEn = $this->str($mapped['description_en'] ?? null);

        if ($titleAr === null && $titleEn === null && $descriptionAr === null && $descriptionEn === null) {
            $errors[] = ['field' => 'title_ar', 'message' => 'At least a title or a description is required.'];
        }

        $legacyRef = $this->str($mapped['legacy_ref'] ?? null);

        $artistCode = $this->str($mapped['artist_code'] ?? null);
        $artistId = null;

        if ($artistCode !== null) {
            $artist = Artist::where('legacy_code', $artistCode)->first();

            if ($artist === null) {
                $errors[] = ['field' => 'artist_code', 'message' => "No artist found with legacy_code {$artistCode}."];
            } else {
                $artistId = $artist->id;
            }
        }

        $mappedData = [
            'legacy_ref' => $legacyRef,
            'item_type' => $itemType,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'creator_name' => $this->str($mapped['creator_name'] ?? null),
            'publication_name_ar' => $this->str($mapped['publication_name_ar'] ?? null),
            'publication_name_en' => $this->str($mapped['publication_name_en'] ?? null),
            'issue_no' => $this->str($mapped['issue_no'] ?? null),
            'page' => $this->str($mapped['page'] ?? null),
            'language' => $this->str($mapped['language'] ?? null),
            'original_format' => $this->str($mapped['original_format'] ?? null),
            'source_filename' => $this->str($mapped['source_filename'] ?? null),
            'content' => $this->parseDate($mapped['content'] ?? null),
            'digitized_at' => $this->str($mapped['digitized_at'] ?? null),
            'rights_status' => $this->str($mapped['rights_status'] ?? null),
            'rights_holder_ar' => $this->str($mapped['rights_holder_ar'] ?? null),
            'rights_holder_en' => $this->str($mapped['rights_holder_en'] ?? null),
            'license' => $this->str($mapped['license'] ?? null),
            'consent_status' => $this->str($mapped['consent_status'] ?? null),
            'artist_id' => $artistId,
        ];

        $match = $errors === []
            ? $this->matcher->resolve($legacyRef)
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

            $item = ArchiveItem::create($this->attributes($data));
            $this->linkArtist($item, $data['artist_id'] ?? null);

            return ['commit_result' => ImportRowCommitResult::Created->value, 'resulting_entity_id' => $item->id];
        }

        if ($row->resolution === ImportRowResolution::LinkExisting->value) {
            $item = ArchiveItem::find($row->matched_entity_id);

            if ($item === null) {
                return ['commit_result' => ImportRowCommitResult::Failed->value, 'resulting_entity_id' => null];
            }

            request()->merge(['edit_summary' => $summary]);
            $item->fill(array_filter($this->attributes($data), fn ($v) => $v !== null));
            $item->save();
            $this->linkArtist($item, $data['artist_id'] ?? null);

            return ['commit_result' => ImportRowCommitResult::Updated->value, 'resulting_entity_id' => $item->id];
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
            'legacy_ref' => $data['legacy_ref'] ?? null,
            'item_type' => $data['item_type'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'creator_name' => $data['creator_name'] ?? null,
            'publication_name_ar' => $data['publication_name_ar'] ?? null,
            'publication_name_en' => $data['publication_name_en'] ?? null,
            'issue_no' => $data['issue_no'] ?? null,
            'page' => $data['page'] ?? null,
            'language' => $data['language'] ?? null,
            'original_format' => $data['original_format'] ?? null,
            'source_filename' => $data['source_filename'] ?? null,
            'content' => PartialDate::fromArray($data['content'] ?? null),
            'digitized_at' => $data['digitized_at'] ?? null,
            'rights_status' => $data['rights_status'] ?? 'unknown',
            'rights_holder_ar' => $data['rights_holder_ar'] ?? null,
            'rights_holder_en' => $data['rights_holder_en'] ?? null,
            'license' => $data['license'] ?? null,
            'consent_status' => $data['consent_status'] ?? 'unknown',
            // D31: imports never auto-publish, and always land at the most
            // restrictive access level, regardless of spreadsheet content.
            'access_level' => 'institution_only',
            'publication_status' => 'draft',
        ];
    }

    private function linkArtist(ArchiveItem $item, ?int $artistId): void
    {
        if ($artistId === null) {
            return;
        }

        $item->links()->firstOrCreate([
            'linkable_type' => Artist::class,
            'linkable_id' => $artistId,
            'role' => 'subject',
        ]);
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
