<?php

namespace App\Support\Ocr;

use App\Enums\OcrRegionType;
use App\Models\File;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Models\OcrHandwritingSuggestion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Handwriting suggestions, end to end:
 *
 *   crop (already stored by the OCR job)
 *   → provider suggestion, on a reviewer's request — never automatically
 *   → stored with provider, model, version, raw result and confidence
 *   → a reviewer accepts, edits or rejects it
 *   → only an accepted or edited text becomes a form field's transcription
 *
 * Nothing here ever writes a suggestion into a field by itself. A reviewer
 * who types a transcription directly is recorded against any pending
 * suggestion too, so every suggestion ends up paired with what a human
 * actually decided — the evaluation data the later training phase needs.
 *
 * Suggestions are keyed by crop content: asking twice, or after re-running
 * OCR (which recreates regions from the same page), never calls twice.
 */
class HandwritingSuggestionService
{
    /** Signatures are never sent: they are not text to transcribe, and they are personal marks. */
    public const ELIGIBLE_TYPES = [OcrRegionType::Handwriting, OcrRegionType::FormValue, OcrRegionType::Unknown];

    public function __construct(private readonly HandwritingOcrProvider $provider) {}

    /** Null when suggestions may be requested for this file's handwriting, otherwise why not. */
    public function refusalFor(File $file): ?string
    {
        if (! config('ocr.handwriting.enabled')) {
            return 'disabled';
        }
        if ($this->provider->name() === NullHandwritingOcrProvider::NAME) {
            return 'no_provider_configured';
        }

        return $this->provider->isExternal() ? ExternalProcessingPolicy::refusalFor($file, 'ocr.handwriting') : null;
    }

    /**
     * For the review UI: whether suggestions can be requested for this file, and why not.
     *
     * @return array{available: bool, reason: ?string, provider: ?string, external: bool}
     */
    public function describe(File $file): array
    {
        $refusal = $this->refusalFor($file);
        $configured = $this->provider->name() !== NullHandwritingOcrProvider::NAME;

        return [
            'available' => $refusal === null,
            'reason' => $refusal,
            'provider' => $configured ? $this->provider->name() : null,
            'external' => $configured && $this->provider->isExternal(),
        ];
    }

    public function regionRefusal(FileOcrRegion $region): ?string
    {
        if (! in_array($region->region_type, self::ELIGIBLE_TYPES, true)) {
            return 'region_type_not_eligible';
        }

        return $region->crop_path === null ? 'no_crop' : null;
    }

    /**
     * @throws ValidationException when policy refuses the request
     * @throws HandwritingOcrException when the provider could not answer (nothing is stored)
     */
    public function suggestForRegion(FileOcrRegion $region, ?User $requestedBy): OcrHandwritingSuggestion
    {
        $file = $region->file;
        $disk = Storage::disk($file->disk);
        $refusal = $this->refusalFor($file) ?? $this->regionRefusal($region);
        if ($refusal === null && ! $disk->exists((string) $region->crop_path)) {
            $refusal = 'no_crop';
        }
        if ($refusal !== null) {
            throw ValidationException::withMessages(['region' => [$refusal]]);
        }
        $bytes = $disk->get((string) $region->crop_path);

        $hash = $region->crop_sha256 ?? hash('sha256', $bytes);
        if ($region->crop_sha256 === null) {
            $region->update(['crop_sha256' => $hash]); // regions cropped before hashes were recorded
        }

        $key = ['file_id' => $file->id, 'crop_sha256' => $hash, 'provider' => $this->provider->name(), 'model' => $this->provider->model()];
        $existing = OcrHandwritingSuggestion::query()->where($key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $path = tempnam(sys_get_temp_dir(), 'hw-crop-').'.png';
        file_put_contents($path, $bytes);
        try {
            $suggestion = $this->provider->suggest($path, $region->language);
        } finally {
            @unlink($path);
        }

        $attributes = [
            ...$key,
            'region_id' => $region->id,
            'crop_path' => $region->crop_path,
            'model_version' => $suggestion->modelVersion,
            'language' => $region->language,
            'status' => $suggestion->text === '' ? OcrHandwritingSuggestion::STATUS_EMPTY : OcrHandwritingSuggestion::STATUS_SUGGESTED,
            'text' => $suggestion->text === '' ? null : $suggestion->text,
            'confidence' => $suggestion->confidence,
            'raw_result' => $suggestion->raw,
            'requested_by_user_id' => $requestedBy?->id,
        ];

        // Every provider call is logged with what produced it — never the text.
        Log::info('Handwriting suggestion provider call', [
            'provider' => $attributes['provider'], 'model' => $attributes['model'], 'model_version' => $attributes['model_version'],
            'file_id' => $file->id, 'region_id' => $region->id, 'crop_sha256' => $hash,
            'status' => $attributes['status'], 'confidence' => $attributes['confidence'], 'chars' => mb_strlen($suggestion->text),
        ]);

        try {
            return OcrHandwritingSuggestion::create($attributes);
        } catch (UniqueConstraintViolationException) {
            return OcrHandwritingSuggestion::query()->where($key)->firstOrFail();
        }
    }

    /** The most recent suggestion for a region's crop, from any provider. */
    public function latestFor(FileOcrRegion $region): ?OcrHandwritingSuggestion
    {
        if ($region->crop_sha256 === null) {
            return null;
        }

        return OcrHandwritingSuggestion::query()
            ->where('file_id', $region->file_id)
            ->where('crop_sha256', $region->crop_sha256)
            ->latest('id')
            ->first();
    }

    /**
     * A reviewer's explicit decision. Accepting or editing also records the
     * text as the transcription of any form field whose value is this crop.
     */
    public function review(OcrHandwritingSuggestion $suggestion, string $decision, ?string $finalText, User $reviewer): OcrHandwritingSuggestion
    {
        $finalText = $finalText !== null ? trim($finalText) : null;

        $final = match ($decision) {
            OcrHandwritingSuggestion::DECISION_ACCEPTED => $suggestion->text,
            OcrHandwritingSuggestion::DECISION_EDITED => $finalText,
            OcrHandwritingSuggestion::DECISION_REJECTED => null,
            default => throw ValidationException::withMessages(['decision' => ['Unknown decision.']]),
        };
        if ($decision === OcrHandwritingSuggestion::DECISION_ACCEPTED && ($final === null || $final === '')) {
            throw ValidationException::withMessages(['decision' => ['There is no suggested text to accept.']]);
        }
        if ($decision === OcrHandwritingSuggestion::DECISION_EDITED && ($final === null || $final === '')) {
            throw ValidationException::withMessages(['final_text' => ['Enter the corrected transcription.']]);
        }
        // Submitting the suggestion unchanged is accepting it, whatever the button said.
        if ($decision === OcrHandwritingSuggestion::DECISION_EDITED && $final === $suggestion->text) {
            $decision = OcrHandwritingSuggestion::DECISION_ACCEPTED;
        }

        return DB::transaction(function () use ($suggestion, $decision, $final, $reviewer) {
            $suggestion->update([
                'decision' => $decision,
                'final_text' => $final,
                'decided_by_user_id' => $reviewer->id,
                'decided_at' => now(),
            ]);

            if ($final !== null) {
                foreach ($this->formFieldsFor($suggestion) as $field) {
                    $field->update(['manual_value' => $final, 'transcribed_by_user_id' => $reviewer->id, 'transcribed_at' => now()]);
                }
            }

            return $suggestion->refresh();
        });
    }

    /**
     * A reviewer typed a transcription directly. If a suggestion for the same
     * crop is still undecided, record what the reviewer's text means for it.
     */
    public function recordTranscription(FileOcrFormField $field, string $value, User $reviewer): void
    {
        $region = $field->valueRegion;
        $suggestion = $region !== null ? $this->latestFor($region) : null;
        if ($suggestion === null || $suggestion->decision !== null) {
            return;
        }

        $suggestion->update([
            'decision' => $suggestion->text !== null && trim($value) === $suggestion->text
                ? OcrHandwritingSuggestion::DECISION_ACCEPTED
                : OcrHandwritingSuggestion::DECISION_EDITED,
            'final_text' => trim($value),
            'decided_by_user_id' => $reviewer->id,
            'decided_at' => now(),
        ]);
    }

    /**
     * Suggestions for every eligible handwriting region of a file, for the
     * command-line batch. Crops already suggested are not sent again.
     *
     * @return array{refused: ?string, eligible: int, skipped: array<string, int>, provider_calls: int, cached: int, would_call: int}
     *
     * @throws HandwritingOcrException when a provider call fails; suggestions stored before it are kept
     */
    public function suggestForFile(File $file, ?User $requestedBy, bool $dryRun = false): array
    {
        $summary = ['refused' => $this->refusalFor($file), 'eligible' => 0, 'skipped' => [], 'provider_calls' => 0, 'cached' => 0, 'would_call' => 0];
        if ($summary['refused'] !== null) {
            return $summary;
        }

        foreach ($file->ocrRegions()->orderBy('page_number')->orderBy('id')->get() as $region) {
            $refusal = $this->regionRefusal($region);
            if ($refusal !== null) {
                $summary['skipped'][$refusal] = ($summary['skipped'][$refusal] ?? 0) + 1;

                continue;
            }
            $summary['eligible']++;

            $cached = $region->crop_sha256 !== null && OcrHandwritingSuggestion::query()->where([
                'file_id' => $file->id, 'crop_sha256' => $region->crop_sha256,
                'provider' => $this->provider->name(), 'model' => $this->provider->model(),
            ])->exists();

            if ($cached) {
                $summary['cached']++;
            } elseif ($dryRun) {
                $summary['would_call']++;
            } else {
                $this->suggestForRegion($region, $requestedBy);
                $summary['provider_calls']++;
            }
        }

        return $summary;
    }

    /**
     * @return iterable<FileOcrFormField>
     */
    private function formFieldsFor(OcrHandwritingSuggestion $suggestion): iterable
    {
        $regionIds = FileOcrRegion::query()
            ->where('file_id', $suggestion->file_id)
            ->where('crop_sha256', $suggestion->crop_sha256)
            ->pluck('id');

        return FileOcrFormField::query()->whereIn('value_region_id', $regionIds)->get();
    }
}
