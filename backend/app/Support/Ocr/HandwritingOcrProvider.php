<?php

namespace App\Support\Ocr;

/**
 * Produces a *suggestion* for a handwriting crop — never a verified value.
 * Callers must always store the result alongside `requires_human_review =
 * true` and must never write it into a field's accepted value without a
 * human explicitly transcribing/confirming it. A null return means "no
 * suggestion available", which is a fully valid, expected outcome — the
 * default binding (NullHandwritingOcrProvider) always returns null.
 */
interface HandwritingOcrProvider
{
    /**
     * @return array{text: string, confidence: int}|null
     */
    public function suggest(string $cropImagePath, string $language): ?array;
}
