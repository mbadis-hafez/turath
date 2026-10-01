<?php

namespace App\Support\Ocr\Correction;

use App\Models\File;
use App\Models\FileOcrRegion;
use App\Models\FileOcrRegionCorrection;
use App\Models\OcrCorrection;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs AI correction over a file's eligible printed-text regions and stores
 * each result as a separate layer beside the OCR text:
 *
 *   file_ocr_regions.source_text        — OCR layer, never modified
 *   ocr_corrections                     — the model's masked answer (the cache)
 *   file_ocr_region_corrections         — corrected layer for one region
 *
 * Nothing here feeds an extracted field or a record: a correction is a
 * suggestion with review reasons attached, and needs_review false only means
 * no check objected.
 *
 * Cost control: a call is keyed on the masked text, language, provider, model,
 * prompt version and rules version. The same key is never sent twice — not
 * across regions, files or re-runs — including when the answer was unusable,
 * since that call was paid for too — nor by two workers at once (see
 * callOnce). Each region is persisted as soon as it's done, so a run
 * interrupted by a provider failure resumes where it stopped.
 */
class OcrCorrectionService
{
    private readonly CorrectionPrompt $prompt;

    private readonly CorrectionGuard $guard;

    private readonly SensitiveTokenMasker $masker;

    public function __construct(
        private readonly OcrCorrectionProvider $provider,
        private readonly CorrectionPolicy $policy = new CorrectionPolicy,
    ) {
        $this->prompt = new CorrectionPrompt;
        $this->masker = new SensitiveTokenMasker;
        $this->guard = new CorrectionGuard($this->masker);
    }

    /**
     * @return array{refused: ?string, eligible: int, skipped: array<string, int>, provider_calls: int, cache_hits: int, statuses: array<string, int>, would_call: int, would_send_chars: int}
     *
     * @throws OcrCorrectionException when a provider call fails; regions finished before it are kept
     */
    public function correctFile(File $file, bool $dryRun = false): array
    {
        $summary = ['refused' => null, 'eligible' => 0, 'skipped' => [], 'provider_calls' => 0, 'cache_hits' => 0, 'statuses' => [], 'would_call' => 0, 'would_send_chars' => 0];

        $summary['refused'] = $this->policy->refusalFor($file, $this->provider);
        if ($summary['refused'] !== null) {
            return $summary;
        }

        foreach ($file->ocrRegions()->orderBy('page_number')->orderBy('id')->get() as $region) {
            $refusal = $this->policy->regionRefusal($region);
            $masked = $this->masker->mask((string) $region->source_text);
            if ($refusal === null && ! $this->hasWords($masked['text'])) {
                $refusal = 'nothing_to_correct';
            }
            if ($refusal !== null) {
                $summary['skipped'][$refusal] = ($summary['skipped'][$refusal] ?? 0) + 1;

                continue;
            }

            $summary['eligible']++;
            $key = $this->cacheKey($masked['text'], $region->language);
            $correction = OcrCorrection::query()->where($key)->first();

            if ($dryRun) {
                $correction !== null ? $summary['cache_hits']++ : $summary['would_call']++;
                $summary['would_send_chars'] += $correction === null ? mb_strlen($masked['text']) : 0;

                continue;
            }

            if ($correction === null) {
                [$correction, $called] = $this->callOnce($masked['text'], $region->language, $key);
                $called ? $summary['provider_calls']++ : $summary['cache_hits']++;
            } else {
                $summary['cache_hits']++;
            }

            $status = $this->link($region, $correction, $masked['tokens'])->status;
            $summary['statuses'][$status] = ($summary['statuses'][$status] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * The cache key a region's text is corrected under.
     *
     * @return array{input_hash: string, provider: string, model: string, prompt_version: string, rules_version: string}
     */
    public function cacheKeyFor(FileOcrRegion $region): array
    {
        return $this->cacheKey($this->masker->mask((string) $region->source_text)['text'], $region->language);
    }

    /**
     * @param  array{input_hash: string, provider: string, model: string, prompt_version: string, rules_version: string}  $key
     */
    public static function lockName(array $key): string
    {
        return 'ocr-correction:'.hash('sha256', implode('|', $key));
    }

    /**
     * One provider call per key, even across workers: a bulk upload of the
     * same form carries identical printed labels and footers, and every
     * worker that missed the cache at the same moment would otherwise pay for
     * the same text. Whoever holds the lock calls; the others wait, then find
     * the stored answer.
     *
     * @param  array{input_hash: string, provider: string, model: string, prompt_version: string, rules_version: string}  $key
     * @return array{0: OcrCorrection, 1: bool} the correction, and whether this call paid for it
     */
    private function callOnce(string $maskedText, ?string $language, array $key): array
    {
        // Held for longer than one provider call can take, so it never expires mid-call.
        $ttl = (int) config('ocr.correction.timeout_seconds', 60) + 30;

        try {
            return Cache::lock(self::lockName($key), $ttl)->block((int) config('ocr.correction.lock_wait_seconds', $ttl), function () use ($maskedText, $language, $key) {
                $stored = OcrCorrection::query()->where($key)->first();

                return $stored !== null ? [$stored, false] : [$this->call($maskedText, $language, $key), true];
            });
        } catch (LockTimeoutException) {
            throw new OcrCorrectionException('Another worker is still correcting the same text.', true);
        }
    }

    /**
     * @return array{input_hash: string, provider: string, model: string, prompt_version: string, rules_version: string}
     */
    private function cacheKey(string $maskedText, ?string $language): array
    {
        return [
            'input_hash' => hash('sha256', json_encode([$maskedText, $language], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'provider' => $this->provider->name(),
            'model' => $this->provider->model(),
            'prompt_version' => CorrectionPrompt::VERSION,
            'rules_version' => CorrectionGuard::RULES_VERSION,
        ];
    }

    /**
     * @param  array{input_hash: string, provider: string, model: string, prompt_version: string, rules_version: string}  $key
     */
    private function call(string $maskedText, ?string $language, array $key): OcrCorrection
    {
        $started = hrtime(true);
        $response = $this->provider->correct(new ProviderRequest(
            system: $this->prompt->system(),
            user: $this->prompt->user($maskedText, $language),
            schema: $this->prompt->schema(),
            schemaName: CorrectionPrompt::SCHEMA_NAME,
            temperature: config('ocr.correction.temperature') === null ? null : (float) config('ocr.correction.temperature'),
        ));
        $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $result = $this->guard->inspect($maskedText, $response);

        $attributes = [
            ...$key,
            'model_version' => $response->modelVersion,
            'language' => $language,
            'input_chars' => mb_strlen($maskedText),
            'status' => $result->usable ? OcrCorrection::STATUS_VALID : OcrCorrection::STATUS_INVALID_OUTPUT,
            'corrected_text' => $result->correctedText,
            'changes' => $result->changes,
            'name_candidates' => $result->nameCandidates,
            'model_confidence' => $result->modelConfidence,
            'model_needs_review' => $result->modelNeedsReview,
            'model_reason' => $result->modelReason,
            'guard_flags' => $result->flags,
            'raw_response' => $response->raw,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'duration_ms' => $durationMs,
        ];

        // Every AI operation is logged with what produced it — never the text.
        Log::info('OCR correction provider call', array_diff_key($attributes, array_flip(['corrected_text', 'changes', 'name_candidates', 'model_reason', 'raw_response'])));

        try {
            return OcrCorrection::create($attributes);
        } catch (UniqueConstraintViolationException) {
            // A concurrent run stored the same key first; keep one row.
            return OcrCorrection::query()->where($key)->firstOrFail();
        }
    }

    /**
     * @param  array<string, string>  $tokens  this region's placeholder => original
     */
    private function link(FileOcrRegion $region, OcrCorrection $correction, array $tokens): FileOcrRegionCorrection
    {
        $flags = $correction->guard_flags ?? [];
        $usable = $correction->status === OcrCorrection::STATUS_VALID && $correction->corrected_text !== null;

        if (! $usable) {
            $attributes = ['status' => FileOcrRegionCorrection::STATUS_REJECTED, 'corrected_text' => null, 'needs_review' => true, 'review_reasons' => $flags];
        } else {
            $reasons = $flags;
            // The model's own estimate can only ever add review, never remove it.
            if ($correction->model_confidence === null || $correction->model_confidence < (float) config('ocr.correction.review_below_confidence')) {
                $reasons[] = 'low_model_confidence';
            }

            $restored = $this->masker->restore((string) $correction->corrected_text, $tokens);
            $changed = $this->normalizeSpace($restored) !== $this->normalizeSpace((string) $region->source_text);

            $attributes = [
                'status' => match (true) {
                    $reasons !== [] => FileOcrRegionCorrection::STATUS_NEEDS_REVIEW,
                    $changed => FileOcrRegionCorrection::STATUS_CORRECTED,
                    default => FileOcrRegionCorrection::STATUS_UNCHANGED,
                },
                'corrected_text' => $restored,
                'needs_review' => $reasons !== [],
                'review_reasons' => array_values(array_unique($reasons)),
            ];
        }

        return FileOcrRegionCorrection::updateOrCreate(
            ['region_id' => $region->id, 'ocr_correction_id' => $correction->id],
            ['file_id' => $region->file_id, ...$attributes],
        );
    }

    /** Whether anything but placeholders, digits and punctuation is left to correct. */
    private function hasWords(string $maskedText): bool
    {
        return preg_match('/\p{L}/u', (string) preg_replace('/⟦[EUN]\d+⟧/u', '', $maskedText)) === 1;
    }

    private function normalizeSpace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
