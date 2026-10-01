<?php

namespace App\Support\Ocr;

use RuntimeException;
use Throwable;

/** No suggestion could be obtained — nothing is stored, so asking again calls the provider again. */
class HandwritingOcrException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
