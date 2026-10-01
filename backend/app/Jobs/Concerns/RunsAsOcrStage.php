<?php

namespace App\Jobs\Concerns;

use App\Enums\OcrStage;
use App\Models\File;
use App\Support\Ocr\Pipeline\OcrPipeline;
use App\Support\Ocr\Pipeline\StageOutcome;
use Closure;
use Throwable;

/**
 * Makes a queued job one stage of the OCR pipeline: it claims the stage
 * before working (so a superseded or duplicate delivery does nothing),
 * records how it ended, and hands over to the next stage.
 *
 * A failed attempt never throws out of the job: it is either released to
 * retry (when the error is worth retrying and attempts remain) or failed on
 * purpose, which still lands it in failed_jobs. That keeps one stage's
 * failure from surfacing as another's under the sync driver, where the next
 * stage runs inside the previous one's dispatch.
 */
trait RunsAsOcrStage
{
    /** The pipeline run that dispatched this job; null when run directly, which takes the stage over. */
    public ?string $runId = null;

    abstract public static function stage(): OcrStage;

    /**
     * @param  Closure(File): StageOutcome  $work
     */
    protected function runStage(File $file, Closure $work): void
    {
        $pipeline = app(OcrPipeline::class);
        $run = $pipeline->claim($file, static::stage(), $this->runId);
        if ($run === null) {
            return;
        }

        try {
            $outcome = $work($file);
        } catch (Throwable $e) {
            $retryIn = $this->isRetryable($e) && $this->attempts() < $this->tries ? $this->retryDelay() : null;
            $pipeline->attemptFailed($run, $e, $retryIn);
            $retryIn !== null ? $this->release($retryIn) : $this->fail($e);

            return;
        }

        $pipeline->finished($run, $outcome);
    }

    /** Whether an error might go away on its own. */
    protected function isRetryable(Throwable $e): bool
    {
        return true;
    }

    /** Called by the queue when it gives up on the job, including on a timeout. */
    public function failed(?Throwable $e): void
    {
        app(OcrPipeline::class)->abandoned($this->fileId, static::stage(), $this->runId, $e);
    }

    private function retryDelay(): int
    {
        $backoff = $this->backoff();

        return $backoff === [] ? 0 : $backoff[min($this->attempts(), count($backoff)) - 1];
    }
}
