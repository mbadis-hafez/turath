<?php

namespace App\Support\Ocr\Pipeline;

use App\Models\FileOcrStageRun;

/** How a stage that ran to the end turned out. A stage that threw has no outcome — see OcrPipeline::attemptFailed(). */
final class StageOutcome
{
    /**
     * @param  array<string, mixed>  $summary  counts only — never document text
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $reason,
        public readonly array $summary,
    ) {}

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function succeeded(array $summary = []): self
    {
        return new self(FileOcrStageRun::STATUS_SUCCEEDED, null, $summary);
    }

    /** The stage looked and found it did not apply, e.g. AI correction is off. */
    public static function skipped(string $reason): self
    {
        return new self(FileOcrStageRun::STATUS_SKIPPED, $reason, []);
    }
}
