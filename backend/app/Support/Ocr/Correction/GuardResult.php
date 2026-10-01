<?php

namespace App\Support\Ocr\Correction;

final readonly class GuardResult
{
    /**
     * @param  array<int, array{original: string, corrected: string, type: string}>  $changes
     * @param  array<int, array{ocr_text: string, candidate: string, kind: string}>  $nameCandidates
     * @param  array<int, string>  $flags
     */
    public function __construct(
        public bool $usable,
        public ?string $correctedText,
        public array $changes,
        public array $nameCandidates,
        public ?float $modelConfidence,
        public bool $modelNeedsReview,
        public ?string $modelReason,
        public array $flags,
    ) {}

    /**
     * @param  array<int, string>  $flags
     */
    public static function unusable(array $flags): self
    {
        return new self(false, null, [], [], null, true, null, $flags);
    }
}
