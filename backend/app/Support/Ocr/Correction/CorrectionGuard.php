<?php

namespace App\Support\Ocr\Correction;

use App\Support\ArabicNormalizer;

/**
 * Deterministic checks on a model's correction, run locally no matter which
 * provider answered or how strictly it claims to enforce the schema. The
 * guard never makes text more trusted than the model said — it only rejects
 * output or adds reasons for a human to look.
 *
 * Works on the masked text (placeholders intact), so its result depends only
 * on the cached provider output and RULES_VERSION and can be cached with it.
 * Bump RULES_VERSION when these checks or SensitiveTokenMasker change.
 */
class CorrectionGuard
{
    public const RULES_VERSION = 'guard-v1';

    /** The output can't be used at all. */
    public const FLAG_SCHEMA_INVALID = 'schema_invalid';

    public const FLAG_PLACEHOLDER_MISMATCH = 'placeholder_mismatch';

    /** The output is usable as a suggestion, but a person must look. */
    public const FLAG_CHANGE_NOT_IN_SOURCE = 'change_not_in_source';

    public const FLAG_UNDECLARED_CHANGE = 'undeclared_change';

    public const FLAG_EXCESSIVE_CHANGE = 'excessive_change';

    public const FLAG_LENGTH_CHANGE = 'length_change';

    public const FLAG_MISLABELED_CHANGE = 'mislabeled_change';

    /** Change types that claim to leave the letters alone — verified, not taken on trust. */
    private const SURFACE_TYPES = ['orthographic_normalization', 'word_spacing', 'punctuation', 'bidi_cleanup'];

    public const FLAG_NAME_CANDIDATES = 'name_candidates';

    public const FLAG_MODEL_FLAGGED = 'model_flagged';

    public const UNUSABLE_FLAGS = [self::FLAG_SCHEMA_INVALID, self::FLAG_PLACEHOLDER_MISMATCH];

    private const MAX_CHANGED_TOKEN_SHARE = 0.35;

    private const MAX_TOKEN_COUNT_SHARE = 0.2;

    private const SMALL_TEXT_TOLERANCE = 2;

    public function __construct(private readonly SensitiveTokenMasker $masker = new SensitiveTokenMasker) {}

    /**
     * @param  string  $maskedSource  exactly the text that was sent
     */
    public function inspect(string $maskedSource, ProviderResponse $response): GuardResult
    {
        if ($response->payload === null) {
            return GuardResult::unusable(['provider_'.($response->problem ?? 'missing_output')]);
        }

        $payload = $this->validatedPayload($response->payload);
        if ($payload === null) {
            return GuardResult::unusable([self::FLAG_SCHEMA_INVALID]);
        }

        $corrected = $payload['corrected_text'];
        $flags = [];

        // Every protected token back exactly as often as it was sent — none dropped, duplicated or invented.
        $sent = $this->masker->placeholderCounts($maskedSource);
        $returned = $this->masker->placeholderCounts($corrected);
        ksort($sent);
        ksort($returned);
        if ($sent !== $returned) {
            $flags[] = self::FLAG_PLACEHOLDER_MISMATCH;
        }

        $changes = array_values(array_filter($payload['changes'], fn (array $c) => $c['original'] !== $c['corrected']));
        $rebuilt = $maskedSource;
        foreach ($changes as $change) {
            // An empty original is an insertion — content the source doesn't have.
            if ($change['original'] === '' || ! str_contains($rebuilt, $change['original'])) {
                $flags[] = self::FLAG_CHANGE_NOT_IN_SOURCE;

                continue;
            }
            $rebuilt = str_replace($change['original'], $change['corrected'], $rebuilt);
        }
        if ($this->normalizeSpace($rebuilt) !== $this->normalizeSpace($corrected)) {
            $flags[] = self::FLAG_UNDECLARED_CHANGE;
        }

        // A change that vanishes under Arabic folding (alef/hamza forms, ة/ه, ى/ي, spacing,
        // punctuation, direction marks) is a surface fix; anything else changed the letters.
        // Only letter changes count toward "rewrote too much", and a change labelled as a
        // surface fix that actually changed letters is itself a reason to look.
        $sourceTokens = $this->tokenCount($maskedSource);
        $changedTokens = 0;
        foreach ($changes as $change) {
            $surfaceOnly = $this->surfaceForm($change['original']) === $this->surfaceForm($change['corrected']);
            if (! $surfaceOnly) {
                $changedTokens += max(1, $this->tokenCount($change['original']));
                if (in_array($change['type'], self::SURFACE_TYPES, true)) {
                    $flags[] = self::FLAG_MISLABELED_CHANGE;
                }
            }
        }
        if ($changedTokens > max(self::SMALL_TEXT_TOLERANCE, $sourceTokens * self::MAX_CHANGED_TOKEN_SHARE)) {
            $flags[] = self::FLAG_EXCESSIVE_CHANGE;
        }
        if (abs($this->tokenCount($corrected) - $sourceTokens) > max(self::SMALL_TEXT_TOLERANCE, $sourceTokens * self::MAX_TOKEN_COUNT_SHARE)) {
            $flags[] = self::FLAG_LENGTH_CHANGE;
        }

        if ($payload['name_candidates'] !== []) {
            $flags[] = self::FLAG_NAME_CANDIDATES;
        }
        if ($payload['needs_review']) {
            $flags[] = self::FLAG_MODEL_FLAGGED;
        }

        return new GuardResult(
            usable: array_intersect($flags, self::UNUSABLE_FLAGS) === [],
            correctedText: $corrected,
            changes: $changes,
            nameCandidates: $payload['name_candidates'],
            modelConfidence: (float) $payload['confidence'],
            modelNeedsReview: $payload['needs_review'],
            modelReason: $payload['reason'],
            flags: array_values(array_unique($flags)),
        );
    }

    /**
     * The structured output, or null if it doesn't match the schema in any
     * respect — a model output that only nearly matches is not trusted.
     *
     * @param  array<string, mixed>  $payload
     * @return array{corrected_text: string, changes: array<int, array{original: string, corrected: string, type: string}>, name_candidates: array<int, array{ocr_text: string, candidate: string, kind: string}>, confidence: int|float, needs_review: bool, reason: ?string}|null
     */
    private function validatedPayload(array $payload): ?array
    {
        $confidence = $payload['confidence'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_string($payload['corrected_text'] ?? null)
            || ! is_array($payload['changes'] ?? null) || ! array_is_list($payload['changes'])
            || ! is_array($payload['name_candidates'] ?? null) || ! array_is_list($payload['name_candidates'])
            || ! (is_int($confidence) || is_float($confidence)) || $confidence < 0 || $confidence > 1
            || ! is_bool($payload['needs_review'] ?? null)
            || ! ($reason === null || is_string($reason))) {
            return null;
        }

        foreach ($payload['changes'] as $change) {
            if (! is_array($change) || ! is_string($change['original'] ?? null) || ! is_string($change['corrected'] ?? null)
                || ! in_array($change['type'] ?? null, CorrectionPrompt::CHANGE_TYPES, true)) {
                return null;
            }
        }
        foreach ($payload['name_candidates'] as $candidate) {
            if (! is_array($candidate) || ! is_string($candidate['ocr_text'] ?? null) || ! is_string($candidate['candidate'] ?? null)
                || ! in_array($candidate['kind'] ?? null, CorrectionPrompt::NAME_KINDS, true)) {
                return null;
            }
        }

        return [
            'corrected_text' => $payload['corrected_text'],
            'changes' => array_map(fn (array $c) => ['original' => $c['original'], 'corrected' => $c['corrected'], 'type' => $c['type']], $payload['changes']),
            'name_candidates' => array_map(fn (array $c) => ['ocr_text' => $c['ocr_text'], 'candidate' => $c['candidate'], 'kind' => $c['kind']], $payload['name_candidates']),
            'confidence' => $confidence,
            'needs_review' => $payload['needs_review'],
            'reason' => $reason,
        ];
    }

    private function surfaceForm(string $text): string
    {
        return ArabicNormalizer::compact((string) preg_replace('/[\x{200E}\x{200F}\x{061C}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text));
    }

    private function normalizeSpace(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function tokenCount(string $text): int
    {
        $text = trim($text);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);
    }
}
