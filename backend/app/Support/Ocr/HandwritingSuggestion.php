<?php

namespace App\Support\Ocr;

final readonly class HandwritingSuggestion
{
    /**
     * @param  float|null  $confidence  0–1 as the provider reports it (mean word confidence); not calibrated, and on the benchmark not predictive of correctness
     * @param  array<string, mixed>  $raw  the provider's own result, kept for audit
     */
    public function __construct(
        public string $text,
        public ?float $confidence,
        public ?string $modelVersion,
        public array $raw,
    ) {}
}
