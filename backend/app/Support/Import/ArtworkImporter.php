<?php

namespace App\Support\Import;

use App\Enums\ImportRowCommitResult;
use App\Enums\ImportRowResolution;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Holder;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Support\DimensionParser;
use App\ValueObjects\PartialDate;

class ArtworkImporter implements Importer
{
    private const UNTITLED_MARKERS = ['untitled', 'دون عنوان'];

    public function __construct(private readonly ArtworkMatchResolver $matcher = new ArtworkMatchResolver) {}

    /**
     * @param  array<string, mixed>  $mapped
     * @return array{mapped_data: array<string, mixed>, match_status: string, matched_entity_id: int|null, match_confidence: string|null, validation_errors: array<int, array{field: string, message: string}>}
     */
    public function validateRow(array $mapped): array
    {
        $errors = [];

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

        $titleAr = $this->str($mapped['title_ar'] ?? null);
        $titleEn = $this->str($mapped['title_en'] ?? null);
        $isUntitled = $this->isUntitledMarker($titleAr) || $this->isUntitledMarker($titleEn) || ($titleAr === null && $titleEn === null);

        if ($isUntitled) {
            $titleAr = null;
            $titleEn = null;
        }

        $category = $this->str($mapped['category'] ?? null);

        if ($category === null) {
            $errors[] = ['field' => 'category', 'message' => 'A category is required.'];
        }

        $dimensionsRaw = $this->str($mapped['dimensions_raw'] ?? null);
        $frameDimensionsRaw = $this->str($mapped['frame_dimensions_raw'] ?? null);
        $dimensions = $dimensionsRaw !== null ? DimensionParser::parse($dimensionsRaw) : null;
        $frameDimensions = $frameDimensionsRaw !== null ? DimensionParser::parse($frameDimensionsRaw) : null;

        $mappedData = [
            'artist_id' => $artistId,
            'attribution_certainty' => $artistId !== null ? 'confirmed' : 'unattributed',
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'is_untitled' => $isUntitled,
            'category' => $category,
            'medium_ar' => $this->str($mapped['medium_ar'] ?? null),
            'medium_en' => $this->str($mapped['medium_en'] ?? null),
            'edition_number' => $this->str($mapped['edition_number'] ?? null),
            'dimensions_raw' => $dimensionsRaw,
            'height_cm' => $dimensions?->heightCm,
            'width_cm' => $dimensions?->widthCm,
            'depth_cm' => $dimensions?->depthCm,
            'dimensions_confidence' => $dimensions?->confidence,
            'frame_dimensions_raw' => $frameDimensionsRaw,
            'frame_height_cm' => $frameDimensions?->heightCm,
            'frame_width_cm' => $frameDimensions?->widthCm,
            'frame_depth_cm' => $frameDimensions?->depthCm,
            'creation' => $this->parseDate($mapped['creation'] ?? null),
            'holder_id' => $this->resolveHolderId($mapped['holder_code'] ?? null),
            'holder_inventory_no' => $this->str($mapped['holder_inventory_no'] ?? null),
            'notes_ar' => $this->str($mapped['notes_ar'] ?? null),
            'notes_en' => $this->str($mapped['notes_en'] ?? null),
        ];

        $match = $errors === []
            ? $this->matcher->resolve($artistId, $titleAr, $titleEn, $dimensions)
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

            $artwork = Artwork::create($this->attributes($data));

            return ['commit_result' => ImportRowCommitResult::Created->value, 'resulting_entity_id' => $artwork->id];
        }

        if ($row->resolution === ImportRowResolution::LinkExisting->value) {
            $artwork = Artwork::find($row->matched_entity_id);

            if ($artwork === null) {
                return ['commit_result' => ImportRowCommitResult::Failed->value, 'resulting_entity_id' => null];
            }

            request()->merge(['edit_summary' => $summary]);
            $artwork->fill(array_filter($this->attributes($data), fn ($v) => $v !== null));
            $artwork->save();

            return ['commit_result' => ImportRowCommitResult::Updated->value, 'resulting_entity_id' => $artwork->id];
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
            'artist_id' => $data['artist_id'] ?? null,
            'attribution_certainty' => $data['attribution_certainty'] ?? 'unattributed',
            'title_ar' => $data['title_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'is_untitled' => $data['is_untitled'] ?? false,
            'category' => $data['category'] ?? null,
            'medium_ar' => $data['medium_ar'] ?? null,
            'medium_en' => $data['medium_en'] ?? null,
            'edition_number' => $data['edition_number'] ?? null,
            'dimensions_raw' => $data['dimensions_raw'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
            'width_cm' => $data['width_cm'] ?? null,
            'depth_cm' => $data['depth_cm'] ?? null,
            'frame_dimensions_raw' => $data['frame_dimensions_raw'] ?? null,
            'frame_height_cm' => $data['frame_height_cm'] ?? null,
            'frame_width_cm' => $data['frame_width_cm'] ?? null,
            'frame_depth_cm' => $data['frame_depth_cm'] ?? null,
            'creation' => PartialDate::fromArray($data['creation'] ?? null),
            'holder_id' => $data['holder_id'] ?? null,
            'holder_inventory_no' => $data['holder_inventory_no'] ?? null,
            'notes_ar' => $data['notes_ar'] ?? null,
            'notes_en' => $data['notes_en'] ?? null,
            // D31: imports never auto-publish, regardless of spreadsheet content.
            'publication_status' => 'draft',
        ];
    }

    private function resolveHolderId(mixed $code): ?int
    {
        $code = $this->str($code);

        if ($code === null) {
            return null;
        }

        return Holder::where('legacy_code', $code)->first()?->id;
    }

    private function parseDate(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return PartialDate::fromString($value)?->toArray();
    }

    private function isUntitledMarker(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), self::UNTITLED_MARKERS, true);
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
