<?php

namespace App\Support\Ocr;

use LogicException;

/**
 * The default: no handwriting provider. Handwriting regions are cropped and
 * transcribed by hand — a fully supported state, not a degraded one.
 * HandwritingSuggestionService refuses to call this, so suggest() is never
 * reached in normal operation.
 */
class NullHandwritingOcrProvider implements HandwritingOcrProvider
{
    public const NAME = 'none';

    public function name(): string
    {
        return self::NAME;
    }

    public function model(): string
    {
        return self::NAME;
    }

    public function isExternal(): bool
    {
        return false;
    }

    public function suggest(string $cropImagePath, ?string $language): HandwritingSuggestion
    {
        throw new LogicException('No handwriting provider is configured.');
    }
}
