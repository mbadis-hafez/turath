<?php

namespace App\Support\Ocr\Correction;

use LogicException;

/**
 * The default: no AI correction at all. CorrectionPolicy refuses to run
 * against it, so correct() is never reached in normal operation.
 */
class NullCorrectionProvider implements OcrCorrectionProvider
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

    public function correct(ProviderRequest $request): ProviderResponse
    {
        throw new LogicException('No OCR correction provider is configured.');
    }
}
