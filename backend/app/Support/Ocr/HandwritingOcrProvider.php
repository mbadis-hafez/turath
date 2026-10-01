<?php

namespace App\Support\Ocr;

/**
 * Reads a handwriting crop and returns a *suggestion* — never a verified
 * value. HandwritingSuggestionService stores it beside the crop and the only
 * way it reaches a field is a reviewer submitting it. No provider at all
 * (NullHandwritingOcrProvider, the default) is a fully supported state:
 * handwriting is then transcribed by hand, as it always has been.
 */
interface HandwritingOcrProvider
{
    /** Recorded with every suggestion and part of its key — e.g. "kraken", "azure". */
    public function name(): string;

    /** The configured model (a local model file's name, or a cloud model id). */
    public function model(): string;

    /** Whether the crop leaves infrastructure we control. Gates the privacy policy. */
    public function isExternal(): bool;

    /**
     * An empty text is a valid answer ("read nothing"); a failure to get any
     * answer throws.
     *
     * @param  string|null  $language  a hint ('ar' | 'en'), or null when unknown — handwriting regions usually are
     *
     * @throws HandwritingOcrException
     */
    public function suggest(string $cropImagePath, ?string $language): HandwritingSuggestion;
}
