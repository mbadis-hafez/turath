<?php

namespace App\Support\Ocr\Correction;

/**
 * Transport to one AI model for OCR correction. Deliberately thin: the prompt,
 * schema, masking and every check on the output live outside the adapter
 * (CorrectionPrompt, SensitiveTokenMasker, CorrectionGuard), so swapping
 * Anthropic for OpenAI, Gemini or a local model changes nothing about what
 * is asked or what is trusted.
 */
interface OcrCorrectionProvider
{
    /** Recorded with every result and part of the cache key — e.g. "anthropic", "openai", "ollama". */
    public function name(): string;

    /** The configured model id (the id the provider reports back is recorded separately). */
    public function model(): string;

    /** Whether text sent to this provider leaves infrastructure we control. Gates the privacy policy. */
    public function isExternal(): bool;

    /**
     * A response whose payload is null means the provider answered but gave
     * nothing usable (refusal, truncation, malformed JSON) — the call still
     * cost money, so the caller records it rather than retrying it.
     *
     * @throws OcrCorrectionException when the call itself failed (network, auth, rate limit, server error)
     */
    public function correct(ProviderRequest $request): ProviderResponse;
}
