<?php

namespace App\Jobs;

use App\Enums\OcrStage;
use App\Jobs\Concerns\RunsAsOcrStage;
use App\Models\File;
use App\Support\Ocr\Correction\OcrCorrectionException;
use App\Support\Ocr\Correction\OcrCorrectionService;
use App\Support\Ocr\Pipeline\StageOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The pipeline's optional AI correction stage (see OcrPipeline and
 * OcrCorrectionService). Queued separately (ocr.pipeline.ai_queue) so a slow
 * or rate-limited provider never holds up OCR of new uploads, and retried
 * with growing delays on rate limits and server errors — each retry resumes
 * where the last stopped, since every region is stored as soon as it's done
 * and a call already paid for is never made again. Failing here never fails
 * the file's OCR.
 */
class CorrectOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsOcrStage, SerializesModels;

    public int $tries = 5;

    public int $timeout = 1800;

    public function __construct(private readonly int $fileId, ?string $runId = null)
    {
        $this->runId = $runId;
        $this->timeout = (int) config('ocr.pipeline.timeouts.correct', $this->timeout);
        $this->onQueue(config('ocr.pipeline.ai_queue'));
    }

    public static function stage(): OcrStage
    {
        return OcrStage::Correct;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(OcrCorrectionService $service): void
    {
        $file = File::find($this->fileId);
        if ($file === null) {
            return;
        }

        $this->runStage($file, function (File $file) use ($service) {
            $summary = $service->correctFile($file);
            if ($summary['refused'] !== null) {
                return StageOutcome::skipped($summary['refused']);
            }

            return StageOutcome::succeeded([
                'eligible' => $summary['eligible'],
                'provider_calls' => $summary['provider_calls'],
                'cache_hits' => $summary['cache_hits'],
                'statuses' => $summary['statuses'],
                'skipped' => $summary['skipped'],
            ]);
        });
    }

    /** Bad requests and auth failures won't fix themselves; rate limits, overload and anything unexpected might. */
    protected function isRetryable(Throwable $e): bool
    {
        return ! $e instanceof OcrCorrectionException || $e->retryable;
    }
}
