<?php

namespace App\Support\Ocr\Dataset;

use App\Enums\ExtractedFieldStatus;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegionCorrection;
use App\Models\OcrHandwritingSuggestion;
use App\Models\OcrReviewExample;
use App\Support\Ocr\ArtistAuthorizationFields;
use App\Support\Ocr\Correction\ValueCorrection;
use App\Support\Ocr\Extraction\ExtractedFieldRoute;
use App\Support\Ocr\Extraction\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Keeps every reviewer decision as an example: what OCR read, what the AI
 * suggested for the same value, what a handwriting model suggested, what the
 * person decided. Written when the decision is made (RecordsOcrReviewDecisions),
 * because re-running OCR replaces the rows the layers live in.
 *
 * Contact details never become examples: a contact field, a contact form
 * field and a handwriting crop of one are skipped, and every text is
 * redacted (DatasetRedactor) on the way in. Recording never blocks the
 * decision itself — a failure is reported and the review goes on.
 */
class ReviewExampleRecorder
{
    public const VERSION = 'examples-v1';

    /** Keys of an authorization letter's contact form fields (ArtistAuthorizationFields). */
    private const CONTACT_KEYS = ['email', 'phone', 'address'];

    public function __construct(
        private readonly DatasetRedactor $redactor,
        private readonly DatasetSplits $splits,
    ) {}

    public function observe(Model $model): void
    {
        try {
            match (true) {
                $model instanceof FileExtractedField && $model->wasChanged('status') => $this->field($model),
                $model instanceof FileExtractedDate && $model->wasChanged('status') => $this->date($model),
                $model instanceof OcrHandwritingSuggestion && $model->wasChanged('decision') && $model->decision !== null => $this->handwriting($model),
                $model instanceof FileOcrFormField && $model->wasChanged('manual_value') && $model->manual_value !== null => $this->formField($model),
                $model instanceof FileEntityMatch && $model->wasChanged('status') => $this->match($model),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function field(FileExtractedField $field): void
    {
        $status = $field->status;
        if (! in_array($status, [ExtractedFieldStatus::Accepted, ExtractedFieldStatus::Rejected, ExtractedFieldStatus::Uncertain], true)) {
            return;
        }
        if (ExtractedFieldRoute::for($field)['route'] === FieldDefinition::ROUTE_ARTIST_CONTACT || $this->isContact($field->formField)) {
            return;
        }

        $machine = $field->extracted_value;
        $final = $status === ExtractedFieldStatus::Accepted ? ($field->verified_value ?? $machine) : null;
        [$ai, $aiMeta] = $this->aiCorrection($field);

        $this->write($field, $field->file, OcrReviewExample::KIND_FIELD, [
            'key' => $field->field_key,
            'ocr_text' => $machine,
            'ai_correction' => $ai,
            'human_correction' => $final,
            'accepted' => match ($status) {
                ExtractedFieldStatus::Accepted => true,
                ExtractedFieldStatus::Rejected => false,
                default => null,
            },
            'reviewer_action' => match ($status) {
                ExtractedFieldStatus::Accepted => $machine === null ? 'transcribed' : ($this->same($final, $machine) ? 'accepted' : 'corrected'),
                ExtractedFieldStatus::Rejected => 'rejected',
                default => 'uncertain',
            },
            'extraction_method' => $field->extraction_method->value,
            'confidence' => $field->confidence,
            'ai_meta' => $aiMeta,
            'reviewer_id' => $field->reviewed_by_user_id,
            'decided_at' => $field->reviewed_at ?? now(),
        ]);
    }

    private function date(FileExtractedDate $date): void
    {
        $status = $date->status;
        if (! in_array($status, [ExtractedFieldStatus::Accepted, ExtractedFieldStatus::Rejected], true)) {
            return;
        }

        $this->write($date, $date->file, OcrReviewExample::KIND_DATE, [
            'key' => $date->field_key ?? $date->date_type->value,
            'ocr_text' => $date->value,
            'human_correction' => $status === ExtractedFieldStatus::Accepted ? $date->value : null,
            'accepted' => $status === ExtractedFieldStatus::Accepted,
            'reviewer_action' => $status === ExtractedFieldStatus::Accepted ? 'accepted' : 'rejected',
            'extraction_method' => $date->source_method,
            'confidence' => $date->confidence,
            'reviewer_id' => $date->reviewed_by_user_id,
            'decided_at' => $date->reviewed_at ?? now(),
        ]);
    }

    private function handwriting(OcrHandwritingSuggestion $suggestion): void
    {
        // A handwritten phone number or address is a contact detail, however it was read.
        $formFields = FileOcrFormField::query()->where('file_id', $suggestion->file_id)
            ->whereHas('valueRegion', fn ($q) => $q->where('crop_sha256', $suggestion->crop_sha256))->get();
        if ($formFields->contains(fn (FileOcrFormField $f) => $this->isContact($f))) {
            return;
        }

        $this->write($suggestion, $suggestion->file, OcrReviewExample::KIND_HANDWRITING, [
            'key' => $formFields->first() !== null ? ((new ArtistAuthorizationFields)->keyFor($formFields->first()->field_label) ?? 'form_field') : 'region',
            'machine_suggestion' => $suggestion->text,
            'human_correction' => $suggestion->final_text,
            'accepted' => $suggestion->decision === OcrHandwritingSuggestion::DECISION_ACCEPTED,
            'reviewer_action' => $suggestion->decision,
            'confidence' => $suggestion->confidence === null ? null : (int) round($suggestion->confidence * 100),
            'ai_meta' => ['kind' => 'handwriting', 'provider' => $suggestion->provider, 'model' => $suggestion->model, 'model_version' => $suggestion->model_version],
            'reviewer_id' => $suggestion->decided_by_user_id,
            'decided_at' => $suggestion->decided_at ?? now(),
        ]);
    }

    private function formField(FileOcrFormField $field): void
    {
        if ($this->isContact($field)) {
            return;
        }
        // A transcription taken from a handwriting suggestion is that suggestion's example.
        $sha = $field->valueRegion?->crop_sha256;
        if ($sha !== null && OcrHandwritingSuggestion::query()->where('file_id', $field->file_id)->where('crop_sha256', $sha)
            ->whereNotNull('decision')->where('final_text', $field->manual_value)->exists()) {
            return;
        }

        $this->write($field, $field->file, OcrReviewExample::KIND_FORM_FIELD, [
            'key' => (new ArtistAuthorizationFields)->keyFor($field->field_label) ?? 'form_field',
            'ocr_text' => $field->machine_value,
            'human_correction' => $field->manual_value,
            'accepted' => $field->machine_value === null ? null : $this->same($field->machine_value, $field->manual_value),
            'reviewer_action' => 'transcribed',
            'extraction_method' => 'manually_transcribed',
            'reviewer_id' => $field->transcribed_by_user_id,
            'decided_at' => $field->transcribed_at ?? now(),
        ]);
    }

    private function match(FileEntityMatch $match): void
    {
        $previous = $match->getOriginal('status');
        if ($match->status === FileEntityMatch::STATUS_PENDING && $previous === FileEntityMatch::STATUS_PENDING) {
            return;
        }

        $candidates = array_map(fn (array $c) => ['id' => $c['id'] ?? null, 'key' => $c['key'] ?? null, 'strength' => $c['strength'] ?? null, 'score' => $c['score'] ?? null], $match->candidates ?? []);
        $top = $candidates[0] ?? null;
        $chosen = $match->confirmed_entity_id ?? $match->confirmed_key;
        $topChosen = $top !== null && $chosen !== null && (string) ($top['id'] ?? $top['key']) === (string) $chosen;

        $this->write($match, $match->file, OcrReviewExample::KIND_MATCH, [
            'key' => $match->entity_type,
            'ocr_text' => $match->source_text,
            'accepted' => match ($match->status) {
                FileEntityMatch::STATUS_CONFIRMED => $topChosen,
                FileEntityMatch::STATUS_NO_MATCH => $top === null ? null : false,
                default => null,
            },
            'reviewer_action' => match ($match->status) {
                FileEntityMatch::STATUS_CONFIRMED => $topChosen ? 'confirmed_top' : ($this->wasCandidate($candidates, $chosen) ? 'confirmed_other_candidate' : 'linked_other'),
                FileEntityMatch::STATUS_NO_MATCH => 'no_match',
                // An undone decision: the latest example says there is none.
                default => 'reset',
            },
            'match' => [
                'candidates' => $candidates,
                'top_id' => $top['id'] ?? $top['key'] ?? null,
                'top_strength' => $top['strength'] ?? null,
                'confirmed_id' => $chosen,
            ],
            'reviewer_id' => $match->reviewed_by_user_id,
            'decided_at' => $match->reviewed_at ?? now(),
        ]);
    }

    /**
     * The AI correction of the field's region, as it stood when the decision was
     * made: the AI's output for this value (unchanged when it changed nothing
     * in it) and which model produced it. Null when no correction ran.
     *
     * @return array{0: ?string, 1: ?array<string, mixed>}
     */
    private function aiCorrection(FileExtractedField $field): array
    {
        if ($field->region_id === null) {
            return [null, null];
        }
        $regionCorrection = FileOcrRegionCorrection::query()->with('ocrCorrection')->where('region_id', $field->region_id)->latest('id')->first();
        $correction = $regionCorrection?->ocrCorrection;
        if ($regionCorrection === null || $correction === null) {
            return [null, null];
        }

        $narrowed = ValueCorrection::narrow($correction, $field->extracted_value);
        $usable = $regionCorrection->status !== FileOcrRegionCorrection::STATUS_REJECTED;

        return [
            $usable ? ($narrowed['suggested_value'] ?? $field->extracted_value) : null,
            [
                'kind' => 'correction',
                'status' => $regionCorrection->status,
                'changed' => $narrowed['suggested_value'] !== null,
                'needs_review' => $regionCorrection->needs_review,
                'review_reasons' => $regionCorrection->review_reasons ?? [],
                'provider' => $correction->provider,
                'model' => $correction->model,
                'model_version' => $correction->model_version,
                'prompt_version' => $correction->prompt_version,
                'rules_version' => $correction->rules_version,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(Model $subject, ?File $file, string $kind, array $attributes): void
    {
        if ($file?->archive_item_id !== null) {
            // The split is fixed when the first example is collected, not when it is exported.
            $this->splits->assign($file->archive_item_id);
        }

        $redacted = false;
        foreach (['ocr_text', 'ai_correction', 'machine_suggestion', 'human_correction'] as $column) {
            $value = $attributes[$column] ?? null;
            $result = $this->redactor->redact(is_string($value) ? $value : null);
            $attributes[$column] = $result['text'];
            $redacted = $redacted || $result['redacted'];
        }

        OcrReviewExample::create([
            ...$attributes,
            'archive_item_id' => $file?->archive_item_id,
            'file_id' => $file?->id,
            'kind' => $kind,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'document_type' => $file?->document_type?->value,
            'language' => $this->language($attributes['human_correction'] ?? $attributes['ocr_text'] ?? $attributes['machine_suggestion'] ?? null),
            'redacted' => $redacted,
            'recorder_version' => self::VERSION,
        ]);
    }

    private function isContact(?FileOcrFormField $field): bool
    {
        if ($field === null) {
            return false;
        }

        return in_array((new ArtistAuthorizationFields)->keyFor($field->field_label), self::CONTACT_KEYS, true);
    }

    /**
     * @param  list<array{id: mixed, key: mixed, strength: mixed, score: mixed}>  $candidates
     */
    private function wasCandidate(array $candidates, mixed $chosen): bool
    {
        foreach ($candidates as $c) {
            if ($chosen !== null && (string) ($c['id'] ?? $c['key']) === (string) $chosen) {
                return true;
            }
        }

        return false;
    }

    private function same(?string $a, ?string $b): bool
    {
        $squash = fn (?string $s) => $s === null ? null : trim((string) preg_replace('/\s+/u', ' ', $s));

        return $squash($a) === $squash($b);
    }

    private function language(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        return match (true) {
            preg_match('/\p{Arabic}/u', $text) === 1 => 'ar',
            preg_match('/[A-Za-z]/', $text) === 1 => 'en',
            default => null,
        };
    }
}
