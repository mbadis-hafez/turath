<?php

namespace App\Support\Ocr\Correction;

use RuntimeException;
use Throwable;

/** The provider call failed outright — nothing was returned, and (unlike an unusable answer) nothing is cached. */
class OcrCorrectionException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Rate limits, overload and server errors are worth retrying; auth and bad requests are not. */
    public static function fromStatus(string $provider, int $status, string $body): self
    {
        $retryable = $status === 408 || $status === 409 || $status === 429 || $status >= 500;

        return new self("{$provider} correction request failed with HTTP {$status}: ".mb_substr($body, 0, 500), $retryable);
    }
}
