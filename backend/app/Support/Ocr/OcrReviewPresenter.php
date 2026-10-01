<?php

namespace App\Support\Ocr;

use App\Enums\OcrRegionType;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileExtractedField;
use App\Models\FileOcrRegion;
use App\Models\FileOcrRegionCorrection;
use App\Support\Ocr\Correction\ValueCorrection;
use App\Support\Ocr\Extraction\ExtractedFieldRoute;
use App\Support\Ocr\Extraction\FieldDefinition;
use Illuminate\Support\Collection;

/**
 * What the review screen shows beside each value, layer by layer: the region
 * it was read from and that region's OCR text, the AI correction of that
 * region applied to just this value (a suggestion — never applied by itself),
 * and, for the archive item's own fields, what the record holds now, so
 * accepting never replaces a value the reviewer didn't see.
 *
 * Also the lines the pipeline set aside for a person that no form field
 * covers — a possible strikethrough, handwriting with no label beside it —
 * which would otherwise never reach the reviewer.
 */
class OcrReviewPresenter
{
    /** The rule recorded on a value a reviewer typed from a set-aside line. */
    public const REVIEWER_RULE = 'reviewer_transcribed';

    /** Region types a person may transcribe a value from; a signature or logo is never text. */
    public const TRANSCRIBABLE = [OcrRegionType::PrintedText, OcrRegionType::FormValue, OcrRegionType::Handwriting, OcrRegionType::Unknown];

    /**
     * @param  Collection<int, FileExtractedField>  $fields
     * @param  Collection<int, FileOcrRegion>  $regions
     * @return array<int, array{source_region: array<string, mixed>|null, ai_correction: array<string, mixed>|null, current_record_value: string|null}> keyed by field id
     */
    public function evidence(File $file, ArchiveItem $item, Collection $fields, Collection $regions): array
    {
        $byId = $regions->keyBy('id');
        // A region can be corrected more than once (a new model or prompt); the latest is shown.
        $corrections = FileOcrRegionCorrection::query()->with('ocrCorrection')->where('file_id', $file->id)->orderBy('id')->get()->keyBy('region_id');

        $out = [];
        foreach ($fields as $field) {
            $region = $field->region_id === null ? null : $byId->get($field->region_id);
            $out[$field->id] = [
                'source_region' => $region === null ? null : $this->region($region),
                'ai_correction' => $region === null ? null : $this->correction($corrections->get($region->id), $field),
                'current_record_value' => ExtractedFieldRoute::for($field)['route'] === FieldDefinition::ROUTE_RECORD
                    ? ExtractedFieldPayloadMapper::currentValue($item, $field->field_key)
                    : null,
            ];
        }

        return $out;
    }

    /**
     * Lines the pipeline wouldn't read by itself and no form field covers.
     *
     * @param  Collection<int, FileOcrRegion>  $regions
     * @param  Collection<int, FileExtractedField>  $fields
     * @return list<array<string, mixed>>
     */
    public function setAside(File $file, Collection $regions, Collection $fields): array
    {
        // Covered: a form field shows it with its own crop, or the extractor already read a value from it.
        $covered = $file->ocrFormFields()->get(['label_region_id', 'value_region_id'])
            ->flatMap(fn ($f) => [$f->label_region_id, $f->value_region_id])
            ->merge($fields->where('rule', '!=', self::REVIEWER_RULE)->pluck('region_id'))
            ->filter()->flip();

        return $regions
            ->filter(fn (FileOcrRegion $r) => $r->requires_human_review && in_array($r->region_type, self::TRANSCRIBABLE, true) && ! $covered->has($r->id))
            ->map(fn (FileOcrRegion $r) => [
                ...$this->region($r),
                'review_reason' => $r->review_reason,
                'correction_marks' => $r->correction_marks ?? [],
                'dismissed' => $r->review_dismissed_at !== null,
                // Values a reviewer already typed from this line.
                'transcribed_field_ids' => $fields->where('region_id', $r->id)->where('rule', self::REVIEWER_RULE)->pluck('id')->values()->all(),
            ])
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function region(FileOcrRegion $region): array
    {
        return [
            'id' => $region->id,
            'page_number' => $region->page_number,
            'region_type' => $region->region_type->value,
            'bbox' => $region->bbox,
            'confidence' => $region->confidence,
            // The engine's reading. For a region it wasn't allowed to read (handwriting) there is none.
            'ocr_text' => $region->source_text,
            'ocr_allowed' => $region->ocr_allowed,
            'has_correction_mark' => $region->has_correction_mark,
            'has_crop' => $region->crop_path !== null,
        ];
    }

    /**
     * The region's AI correction, narrowed to this field's value (ValueCorrection).
     *
     * @return array<string, mixed>|null
     */
    private function correction(?FileOcrRegionCorrection $regionCorrection, FileExtractedField $field): ?array
    {
        $correction = $regionCorrection?->ocrCorrection;
        if ($regionCorrection === null || $correction === null) {
            return null;
        }
        $narrowed = ValueCorrection::narrow($correction, $field->extracted_value);

        return [
            'status' => $regionCorrection->status,
            'needs_review' => $regionCorrection->needs_review,
            'review_reasons' => $regionCorrection->review_reasons ?? [],
            'corrected_line' => $regionCorrection->corrected_text,
            'suggested_value' => $narrowed['suggested_value'],
            'changes' => $narrowed['changes'],
            // The model's reading of a name: for matching and a person, never a spelling fix.
            'name_candidates' => $narrowed['name_candidates'],
            'provider' => $correction->provider,
            'model' => $correction->model,
            'model_version' => $correction->model_version,
            'prompt_version' => $correction->prompt_version,
        ];
    }
}
