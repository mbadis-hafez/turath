<?php

namespace App\ValueObjects;

readonly class ParsedDimensions
{
    public function __construct(
        public ?float $heightCm = null,
        public ?float $widthCm = null,
        public ?float $depthCm = null,
        public string $unitDetected = 'unknown',
        public string $confidence = 'low',
        public ?string $raw = null,
    ) {}
}
