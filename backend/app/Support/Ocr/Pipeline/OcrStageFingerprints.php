<?php

namespace App\Support\Ocr\Pipeline;

use App\Enums\OcrStage;
use App\Jobs\ExtractOcrFieldsJob;
use App\Jobs\ProcessFileOcrJob;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\ArtistNameVariant;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedField;
use App\Models\Holder;
use App\Models\Source;
use App\Support\Ocr\Correction\CorrectionGuard;
use App\Support\Ocr\Correction\CorrectionPrompt;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\Matching\EntityMatcher;
use App\Support\Ocr\Matching\EntityMatchingService;

/**
 * A hash of everything a stage's output depends on. When it matches the
 * fingerprint recorded by the stage's last successful run, running the stage
 * again would reproduce what is already stored, so the pipeline skips it.
 *
 * Code changes are covered by each stage's VERSION constant: bump it when a
 * change should reprocess files that were already done.
 */
class OcrStageFingerprints
{
    public function for(OcrStage $stage, File $file): string
    {
        $inputs = match ($stage) {
            OcrStage::Recognize => $this->recognize($file),
            OcrStage::Extract => $this->extract($file),
            OcrStage::MatchEntities => $this->matchEntities($file),
            OcrStage::Correct => $this->correct($file),
        };

        return hash('sha256', json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * The file's bytes: the same content OCRs the same way.
     *
     * @return array<int, mixed>
     */
    private function recognize(File $file): array
    {
        return [ProcessFileOcrJob::VERSION, $file->sha256, $file->mime_type];
    }

    /**
     * Everything extraction reads: the page text, the regions (their text,
     * and their ids, which provenance points at), the form fields including
     * any transcription, and the document type when a reviewer chose it.
     *
     * @return array<int, mixed>
     */
    private function extract(File $file): array
    {
        $texts = $file->extractedTexts()->orderBy('page_number')->orderBy('language')->get()
            ->map(fn ($t) => [$t->page_number, $t->language, hash('sha256', (string) $t->text), $t->confidence])
            ->all();
        $regions = $file->ocrRegions()->orderBy('id')->get()
            ->map(fn ($r) => [$r->id, $r->page_number, $r->region_type->value, $r->ocr_allowed, $r->has_correction_mark, $r->confidence, $r->bbox, $r->crop_path, hash('sha256', (string) $r->source_text)])
            ->all();
        $formFields = $file->ocrFormFields()->orderBy('id')->get()
            ->map(fn ($f) => [$f->id, $f->field_label, $f->value_region_id, $f->requires_manual_transcription, hash('sha256', (string) $f->machine_value), hash('sha256', (string) $f->manual_value)])
            ->all();
        $chosenType = $file->document_type_set_by_user_id !== null ? $file->document_type?->value : null;

        return [ExtractOcrFieldsJob::VERSION, $texts, $regions, $formFields, $chosenType];
    }

    /**
     * The names to match, the reviewers' decisions so far (a confirmed artist
     * ranks that artist's artworks), what the document says for context, and
     * a watermark of every table candidates come from — adding or editing an
     * artist makes earlier "no candidates" answers stale.
     *
     * @return array<int, mixed>
     */
    private function matchEntities(File $file): array
    {
        $mentions = array_map(fn (array $m) => [$m['field']->id, $m['type'], hash('sha256', $m['value'])], app(EntityMatchingService::class)->mentions($file));
        $decided = FileEntityMatch::query()->where('file_id', $file->id)->where('status', '!=', FileEntityMatch::STATUS_PENDING)->orderBy('id')->get()
            ->map(fn (FileEntityMatch $m) => [$m->extracted_field_id, $m->status, $m->confirmed_entity_id, $m->confirmed_key])->all();
        $links = ArchiveItemLink::query()->where('archive_item_id', $file->archive_item_id)->orderBy('id')->get()
            ->map(fn (ArchiveItemLink $l) => [$l->linkable_type, $l->linkable_id])->all();
        $context = $file->extractedFields()->whereIn('field_key', ['dimensions', 'city'])->orderBy('id')->get()
            ->map(fn (FileExtractedField $f) => [$f->field_key, hash('sha256', (string) $f->currentValue())])->all();
        $years = $file->extractedDates()->orderBy('id')->pluck('normalized')->all();

        $watermarks = [];
        foreach ([Artist::class, ArtistNameVariant::class, Artwork::class, Event::class, Holder::class, Source::class] as $model) {
            $watermarks[] = [$model::query()->count(), (string) $model::query()->max('updated_at')];
        }

        return [EntityMatcher::VERSION, $mentions, $decided, $links, $context, $years, $watermarks];
    }

    /**
     * The regions (re-running OCR recreates them, and corrections are linked
     * per region) and everything that decides a correction's result or
     * status. The provider-call cache is keyed separately, so a changed
     * fingerprint here re-links from cache rather than paying again.
     *
     * @return array<int, mixed>
     */
    private function correct(File $file): array
    {
        $provider = app(OcrCorrectionProvider::class);
        $regions = $file->ocrRegions()->orderBy('id')->get()
            ->map(fn ($r) => [$r->id, $r->region_type->value, $r->language, $r->ai_correction_allowed, $r->has_correction_mark, hash('sha256', (string) $r->source_text)])
            ->all();

        return [
            $provider->name(), $provider->model(), CorrectionPrompt::VERSION, CorrectionGuard::RULES_VERSION,
            (float) config('ocr.correction.review_below_confidence'), (int) config('ocr.correction.max_region_chars'),
            $regions,
        ];
    }
}
