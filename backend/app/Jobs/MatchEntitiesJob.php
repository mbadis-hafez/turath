<?php

namespace App\Jobs;

use App\Enums\OcrStage;
use App\Jobs\Concerns\RunsAsOcrStage;
use App\Models\File;
use App\Support\Ocr\Matching\EntityMatchingService;
use App\Support\Ocr\Pipeline\StageOutcome;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The pipeline's match stage (see OcrPipeline): candidate records for every
 * extracted name or title (EntityMatchingService). Reads and writes only the
 * matches — no record is changed — and runs again when the extracted fields,
 * a reviewer's decision, or the records themselves change.
 */
class MatchEntitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsAsOcrStage, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(private readonly int $fileId, ?string $runId = null)
    {
        $this->runId = $runId;
        $this->timeout = (int) config('ocr.pipeline.timeouts.match', $this->timeout);
        $this->onQueue(config('ocr.pipeline.queue'));
    }

    public static function stage(): OcrStage
    {
        return OcrStage::MatchEntities;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(EntityMatchingService $matching): void
    {
        $file = File::find($this->fileId);
        if ($file === null) {
            return;
        }

        $this->runStage($file, fn (File $file) => StageOutcome::succeeded($matching->matchFile($file)));
    }
}
