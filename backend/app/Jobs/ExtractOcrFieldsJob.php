<?php

namespace App\Jobs;

use App\Enums\DateCalendar;
use App\Enums\DocumentType;
use App\Enums\ExtractedDateType;
use App\Enums\ExtractedFieldStatus;
use App\Enums\ExtractionMethod;
use App\Enums\OcrRegionType;
use App\Enums\OcrStage;
use App\Jobs\Concerns\RunsAsOcrStage;
use App\Models\File;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileOcrRegion;
use App\Support\ArabicDigits;
use App\Support\ArabicNormalizer;
use App\Support\Ocr\DateExtractor;
use App\Support\Ocr\DocumentTypeClassifier;
use App\Support\Ocr\Extraction\DocumentFieldExtractor;
use App\Support\Ocr\Extraction\DocumentFieldSchema;
use App\Support\Ocr\FieldExtractionHeuristic;
use App\Support\Ocr\Pipeline\StageOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * The pipeline's extract stage (see OcrPipeline). Reads only what the
 * recognize stage stored, so it can re-run — after extraction rules change,
 * a reviewer corrects the document type, or a handwritten value is
 * transcribed — without OCR'ing the file again. It produces:
 *
 * - the document type (the classifier's guess, unless a reviewer chose one);
 * - the archive item's own candidate fields (title, date), as before;
 * - the document type's schema fields (Extraction\DocumentFieldSchema), read
 *   from printed regions and form fields with their provenance;
 * - dates with semantic roles, each tied to the printed region it was read
 *   from where there is one.
 *
 * Everything it writes is replaced as a whole in one transaction, except what
 * a reviewer has already accepted, rejected or edited, which is kept.
 */
class ExtractOcrFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsOcrStage, SerializesModels;

    /** Bump when a change to date, field or document-type extraction should re-extract files already done. */
    public const VERSION = 'extract-v2';

    /** Region types whose text is read as printed; handwriting, signatures and images carry none. */
    private const TEXT_REGION_TYPES = [OcrRegionType::PrintedText, OcrRegionType::FormLabel];

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private readonly int $fileId, ?string $runId = null)
    {
        $this->runId = $runId;
        $this->timeout = (int) config('ocr.pipeline.timeouts.extract', $this->timeout);
        $this->onQueue(config('ocr.pipeline.queue'));
    }

    public static function stage(): OcrStage
    {
        return OcrStage::Extract;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $file = File::find($this->fileId);
        if ($file === null) {
            return;
        }

        $this->runStage($file, fn (File $file) => $this->extract($file));
    }

    private function extract(File $file): StageOutcome
    {
        $pagesByLanguage = ['ar' => [], 'en' => []];
        foreach ($file->extractedTexts()->orderBy('page_number')->get() as $text) {
            $pagesByLanguage[$text->language][$text->page_number] = ['text' => (string) $text->text, 'confidence' => (int) $text->confidence];
        }

        $chosenByReviewer = $file->document_type_set_by_user_id !== null && $file->document_type !== null;
        $type = $chosenByReviewer ? $file->document_type : (new DocumentTypeClassifier)->classify($pagesByLanguage);

        $regions = $file->ocrRegions()->get();
        $candidates = [
            ...$this->recordCandidates((new FieldExtractionHeuristic)->extract($pagesByLanguage)),
            ...$this->schemaCandidates($type, (new DocumentFieldExtractor)->extract($type, $this->lines($regions, $pagesByLanguage), $this->formFields($file))),
        ];
        $dates = $this->withRegions((new DateExtractor)->extract($pagesByLanguage, $type), $regions);

        [$created, $kept] = DB::transaction(function () use ($file, $dates, $candidates, $type, $chosenByReviewer) {
            $this->saveDates($file, $dates);
            $counts = $this->saveCandidateFields($file, $candidates);
            if (! $chosenByReviewer) {
                $file->update(['document_type' => $type->value]);
            }

            return $counts;
        });

        return StageOutcome::succeeded([
            'document_type' => $type->value,
            'document_type_source' => $chosenByReviewer ? 'reviewer' : 'classifier',
            'candidate_fields' => $created,
            'schema_fields' => count(array_filter($candidates, fn ($c) => $c['document_type'] !== null)),
            'reviewed_fields_kept' => $kept,
            'dates' => count($dates),
        ]);
    }

    /**
     * Printed lines in reading order: top to bottom, right to left. Only
     * regions whose text is trusted as printed, and none carrying a possible
     * correction mark. A file with no regions at all (processed before region
     * detection) falls back to the stronger of its two page-text passes.
     *
     * @param  Collection<int, FileOcrRegion>  $regions
     * @param  array<string, array<int, array{text: string, confidence: int}>>  $pagesByLanguage
     * @return list<array{page: int, region_id: ?int, text: string, confidence: ?int}>
     */
    private function lines(Collection $regions, array $pagesByLanguage): array
    {
        if ($regions->isEmpty()) {
            $average = fn (array $pages) => $pages === [] ? -1 : array_sum(array_column($pages, 'confidence')) / count($pages);
            $pages = $average($pagesByLanguage['ar']) >= $average($pagesByLanguage['en']) ? $pagesByLanguage['ar'] : $pagesByLanguage['en'];
            $lines = [];
            foreach ($pages as $page => $text) {
                foreach (preg_split('/\r\n|\r|\n/', $text['text']) ?: [] as $line) {
                    $lines[] = ['page' => (int) $page, 'region_id' => null, 'text' => $line, 'confidence' => $text['confidence']];
                }
            }

            return $lines;
        }

        $readable = $regions
            ->filter(fn (FileOcrRegion $r) => $r->ocr_allowed && ! $r->has_correction_mark && in_array($r->region_type, self::TEXT_REGION_TYPES, true) && trim((string) $r->source_text) !== '')
            ->sort(fn (FileOcrRegion $a, FileOcrRegion $b) => [$a->page_number, $a->bbox['y'], -$a->bbox['x']] <=> [$b->page_number, $b->bbox['y'], -$b->bbox['x']]);

        $lines = [];
        foreach ($readable as $region) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $region->source_text) ?: [] as $line) {
                if (trim($line) !== '') {
                    $lines[] = ['page' => $region->page_number, 'region_id' => $region->id, 'text' => $line, 'confidence' => $region->confidence];
                }
            }
        }

        return $lines;
    }

    /**
     * A reviewer's transcription wins over the machine's reading; a
     * handwritten value nobody has transcribed yet has no value at all.
     *
     * @return list<array{id: int, label: string, value: ?string, method: ?string, raw: ?string, page: ?int, region_id: ?int, crop_path: ?string, confidence: ?int}>
     */
    private function formFields(File $file): array
    {
        $fields = [];
        foreach ($file->ocrFormFields()->with(['labelRegion', 'valueRegion'])->orderBy('id')->get() as $field) {
            [$value, $method] = match (true) {
                $field->manual_value !== null => [$field->manual_value, ExtractionMethod::ManuallyTranscribed->value],
                $field->machine_value !== null && ! $field->requires_manual_transcription => [$field->machine_value, ExtractionMethod::OcrDerived->value],
                default => [null, null],
            };
            $fields[] = [
                'id' => $field->id,
                'label' => $field->field_label,
                'value' => $value,
                'method' => $method,
                'raw' => $field->machine_value,
                'page' => $field->valueRegion->page_number ?? $field->labelRegion?->page_number,
                'region_id' => $field->value_region_id,
                'crop_path' => $field->valueRegion?->crop_path,
                'confidence' => $field->valueRegion?->confidence,
            ];
        }

        return $fields;
    }

    /**
     * Ties each date to the printed region it appears in, when it does. A
     * date found only in the whole-page text may have been read off
     * handwriting, so it is kept at lower confidence.
     *
     * @param  array<int, array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int, field_key: ?string, normalized: ?string, context: string}>  $dates
     * @param  Collection<int, FileOcrRegion>  $regions
     * @return list<array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int, field_key: ?string, normalized: ?string, context: string, region_id: ?int, confidence: int}>
     */
    private function withRegions(array $dates, Collection $regions): array
    {
        $printed = $regions->filter(fn (FileOcrRegion $r) => $r->ocr_allowed && $r->source_text !== null);

        return array_values(array_map(function (array $date) use ($printed) {
            $value = ArabicDigits::toAscii($date['value']);
            $region = $printed->first(fn (FileOcrRegion $r) => $r->page_number === $date['source_page'] && str_contains(ArabicDigits::toAscii((string) $r->source_text), $value));

            return [...$date, 'region_id' => $region?->id, 'confidence' => $region !== null ? 80 : 50];
        }, $dates));
    }

    /**
     * @param  array<int, array{field_key: string, extracted_value: string, confidence: int, source_page: int|null}>  $candidates
     * @return list<array{field_key: string, document_type: ?string, multiple: bool, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>
     */
    private function recordCandidates(array $candidates): array
    {
        return array_values(array_map(fn (array $c) => [
            'field_key' => $c['field_key'],
            'document_type' => null,
            'multiple' => false,
            'value' => $c['extracted_value'],
            'confidence' => $c['confidence'],
            'page' => $c['source_page'],
            'region_id' => null,
            'form_field_id' => null,
            'method' => ExtractionMethod::OcrDerived->value,
            'rule' => $c['field_key'] === 'date_display' ? 'year' : 'first_line',
            'original' => null,
            'crop_path' => null,
        ], $candidates));
    }

    /**
     * @param  list<array{field_key: string, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>  $candidates
     * @return list<array{field_key: string, document_type: ?string, multiple: bool, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>
     */
    private function schemaCandidates(DocumentType $type, array $candidates): array
    {
        return array_map(fn (array $c) => [
            ...$c,
            'document_type' => $type->value,
            'multiple' => DocumentFieldSchema::field($type, $c['field_key'])?->isMultiple() ?? false,
        ], $candidates);
    }

    /**
     * A date a reviewer already decided is kept, and not suggested again.
     *
     * @param  list<array{value: string, calendar: DateCalendar, date_type: ExtractedDateType, source_page: ?int, field_key: ?string, normalized: ?string, context: string, region_id: ?int, confidence: int}>  $dates
     */
    private function saveDates(File $file, array $dates): void
    {
        $reviewed = $file->extractedDates()->where('status', '!=', ExtractedFieldStatus::Pending->value)->get();
        $file->extractedDates()->where('status', ExtractedFieldStatus::Pending->value)->delete();

        foreach ($dates as $date) {
            if ($reviewed->contains(fn (FileExtractedDate $r) => $r->source_page === $date['source_page'] && $r->value === $date['value'])) {
                continue;
            }
            FileExtractedDate::create([
                'file_id' => $file->id,
                'value' => $date['value'],
                'normalized' => $date['normalized'],
                'calendar' => $date['calendar']->value,
                'date_type' => $date['date_type']->value,
                'field_key' => $date['field_key'],
                'source_page' => $date['source_page'],
                'source_method' => 'ocr',
                'region_id' => $date['region_id'],
                'context' => mb_substr($date['context'], 0, 255),
                'confidence' => $date['confidence'],
                'status' => ExtractedFieldStatus::Pending->value,
            ]);
        }
    }

    /**
     * Reprocessing must not clobber fields a reviewer already accepted,
     * rejected, or edited: a single-value field a reviewer decided isn't
     * suggested again, and neither is a list item they decided.
     *
     * @param  list<array{field_key: string, document_type: ?string, multiple: bool, value: ?string, confidence: int, page: ?int, region_id: ?int, form_field_id: ?int, method: string, rule: string, original: ?string, crop_path: ?string}>  $candidates
     * @return array{0: int, 1: int} rows created, and candidates left alone because a reviewer already decided them
     */
    private function saveCandidateFields(File $file, array $candidates): array
    {
        $reviewed = $file->extractedFields()->where('status', '!=', ExtractedFieldStatus::Pending->value)->get();
        $file->extractedFields()->where('status', ExtractedFieldStatus::Pending->value)->delete();

        $taken = [];
        foreach ($reviewed as $row) {
            $taken[$row->field_key][$row->ordinal] = true;
        }

        [$created, $kept] = [0, 0];
        foreach ($candidates as $candidate) {
            $sameKey = $reviewed->where('field_key', $candidate['field_key']);
            $decided = $candidate['multiple']
                ? $sameKey->contains(fn (FileExtractedField $r) => ArabicNormalizer::compact((string) $r->extracted_value) === ArabicNormalizer::compact((string) $candidate['value']))
                : $sameKey->isNotEmpty();
            if ($decided) {
                $kept++;

                continue;
            }

            $ordinal = 0;
            while (isset($taken[$candidate['field_key']][$ordinal])) {
                $ordinal++;
            }
            $taken[$candidate['field_key']][$ordinal] = true;

            FileExtractedField::create([
                'file_id' => $file->id,
                'field_key' => $candidate['field_key'],
                'document_type' => $candidate['document_type'],
                'ordinal' => $ordinal,
                'extracted_value' => $candidate['value'],
                'confidence' => $candidate['confidence'],
                'source_page' => $candidate['page'],
                'region_id' => $candidate['region_id'],
                'form_field_id' => $candidate['form_field_id'],
                'extraction_method' => $candidate['method'],
                'rule' => $candidate['rule'],
                'original_ocr_text' => $candidate['original'],
                'crop_path' => $candidate['crop_path'],
                'status' => ExtractedFieldStatus::Pending->value,
            ]);
            $created++;
        }

        return [$created, $kept];
    }
}
