<?php

namespace App\Support\Ocr\Pipeline;

use App\Enums\FileOcrStatus;
use App\Enums\OcrStage;
use App\Jobs\CorrectOcrJob;
use App\Jobs\ExtractOcrFieldsJob;
use App\Jobs\MatchEntitiesJob;
use App\Jobs\ProcessFileOcrJob;
use App\Models\File;
use App\Models\FileOcrStageRun;
use App\Support\Ocr\Correction\CorrectionPolicy;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\Matching\EntityMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The OCR pipeline: recognize → extract → match → correct, each stage its own queued
 * job with its own retries, and each stage's state kept in
 * file_ocr_stage_runs.
 *
 * - Idempotent: a stage whose input is unchanged since its last successful
 *   run is skipped (see OcrStageFingerprints), so re-running the pipeline
 *   never redoes OCR, extraction or AI calls for nothing.
 * - Resumable: starting again picks up at the first stage that isn't done.
 * - One stage at a time: a stage can be re-run on its own; the stages after
 *   it then run only if their input changed.
 * - Failure isolation: a core stage failing fails the file's OCR; an
 *   optional stage (AI correction) failing never does.
 * - Superseding: a pipeline run has an id; a queued job left over from an
 *   older run finds its id replaced and does nothing.
 *
 * The file's own ocr_status/ocr_progress_pct keep their meaning for the
 * review UI: processing while a core stage is under way, completed once all
 * core stages are done.
 */
class OcrPipeline
{
    public const STARTED = 'started';

    public const UP_TO_DATE = 'up_to_date';

    public const BUSY = 'busy';

    public const PREREQUISITES_MISSING = 'prerequisites_missing';

    public const NOT_OCR_CANDIDATE = 'not_ocr_candidate';

    /** Extra time past a stage's own timeout before a running stage counts as abandoned (its worker died). */
    private const RUNNING_GRACE_SECONDS = 300;

    public function __construct(private readonly OcrStageFingerprints $fingerprints) {}

    /**
     * Brings the file's OCR up to date from $from on. $force runs $from even
     * when its input is unchanged; later stages still run only if theirs changed.
     *
     * @return self::STARTED|self::UP_TO_DATE|self::BUSY|self::PREREQUISITES_MISSING|self::NOT_OCR_CANDIDATE
     */
    public function start(File $file, OcrStage $from = OcrStage::Recognize, bool $force = false): string
    {
        if (! $file->isOcrCandidate()) {
            return self::NOT_OCR_CANDIDATE;
        }

        $runId = DB::transaction(function () use ($file, $from) {
            // Two clicks on "run" must not both get through.
            File::query()->whereKey($file->id)->lockForUpdate()->first();
            $file->refresh();

            if ($this->isBusy($file)) {
                return self::BUSY;
            }
            if (! $this->prerequisitesMet($file, $from)) {
                return self::PREREQUISITES_MISSING;
            }

            $runId = (string) Str::uuid();
            foreach ($from->andAfter() as $stage) {
                FileOcrStageRun::query()->updateOrCreate(
                    ['file_id' => $file->id, 'stage' => $stage->value],
                    ['run_id' => $runId, 'status' => FileOcrStageRun::STATUS_PENDING, 'reason' => null, 'error' => null, 'attempts' => 0],
                );
            }

            return $runId;
        });

        if ($runId === self::BUSY || $runId === self::PREREQUISITES_MISSING) {
            return $runId;
        }

        Log::info('OCR pipeline started', ['file_id' => $file->id, 'from' => $from->value, 'force' => $force, 'run_id' => $runId]);

        return $this->continueFrom($file, $from, $runId, $force, $from->isCore());
    }

    /**
     * What start() would do, without doing it.
     *
     * @return array<string, string> stage => up_to_date | will_run | after_earlier_stages | skip:<reason>
     */
    public function plan(File $file, OcrStage $from = OcrStage::Recognize, bool $force = false): array
    {
        // Stages update the file through their own copies (its document type, for one); read it as stored.
        $file = File::query()->findOrFail($file->id);
        $plan = [];
        $earlierWillRun = false;
        foreach ($from->andAfter() as $stage) {
            if ($earlierWillRun) {
                // Its input is whatever the earlier stage produces, so it's decided then.
                $plan[$stage->value] = 'after_earlier_stages';

                continue;
            }
            if (! $force && $this->isUpToDate($this->runFor($file, $stage), $stage, $file)) {
                $plan[$stage->value] = 'up_to_date';
            } else {
                $refusal = $this->preflight($stage, $file);
                $plan[$stage->value] = $refusal !== null ? "skip:{$refusal}" : 'will_run';
                $earlierWillRun = $refusal === null;
            }
            $force = false;
        }

        return $plan;
    }

    /** Whether a stage is under way that a new run must not collide with. */
    public function isBusy(File $file): bool
    {
        $runs = $file->ocrStageRuns()->get();
        if ($runs->isEmpty()) {
            // A file processed by a job queued before stage tracking existed.
            return in_array($file->ocr_status, [FileOcrStatus::Pending, FileOcrStatus::Processing], true)
                && $file->updated_at?->gt(now()->subMinutes($this->queuedStaleMinutes())) === true;
        }

        return $runs->contains(fn (FileOcrStageRun $run) => ! $this->isStale($run) && (
            $run->status === FileOcrStageRun::STATUS_RUNNING
            // A queued optional stage (e.g. correction waiting out a rate limit) is simply superseded.
            || ($run->status === FileOcrStageRun::STATUS_QUEUED && $run->stage->isCore())
        ));
    }

    /** Whether every core stage before $stage has produced output this stage can work from. */
    public function prerequisitesMet(File $file, OcrStage $stage): bool
    {
        foreach ($stage->before() as $earlier) {
            if (! $earlier->isCore()) {
                continue;
            }
            $run = $this->runFor($file, $earlier);
            $done = $run === null
                // Processed before stage tracking existed.
                ? $file->ocr_status === FileOcrStatus::Completed
                : $run->status === FileOcrStageRun::STATUS_SUCCEEDED || ($run->status === FileOcrStageRun::STATUS_SKIPPED && $run->input_fingerprint !== null);
            if (! $done) {
                return false;
            }
        }

        return true;
    }

    /**
     * Called by a stage job as it starts. Returns null when the job must not
     * run: its pipeline run was superseded, the stage already ran, or another
     * worker is running it right now.
     */
    public function claim(File $file, OcrStage $stage, ?string $runId): ?FileOcrStageRun
    {
        $run = DB::transaction(function () use ($file, $stage, $runId) {
            $run = FileOcrStageRun::query()->where('file_id', $file->id)->where('stage', $stage->value)->lockForUpdate()->first();

            if ($runId !== null) {
                $claimable = $run !== null && $run->run_id === $runId && (
                    $run->status === FileOcrStageRun::STATUS_QUEUED
                    // Its previous delivery's worker died mid-run.
                    || ($run->status === FileOcrStageRun::STATUS_RUNNING && $this->isStale($run))
                );
                if (! $claimable) {
                    return null;
                }
            } elseif ($run !== null && $run->status === FileOcrStageRun::STATUS_RUNNING && ! $this->isStale($run)) {
                // A job run directly (or queued before stage tracking) takes the stage over — unless it's running.
                return null;
            }

            $run ??= new FileOcrStageRun(['file_id' => $file->id, 'stage' => $stage]);
            $run->fill([
                'run_id' => $runId ?? (string) Str::uuid(),
                'status' => FileOcrStageRun::STATUS_RUNNING,
                'reason' => null,
                // Cleared while running: the stored output is about to change.
                'input_fingerprint' => null,
                'attempts' => $runId === null ? 1 : $run->attempts + 1,
                'started_at' => now(),
                'finished_at' => null,
            ])->save();

            return $run;
        });

        if ($run === null) {
            Log::info('OCR stage job not run: superseded, already handled, or running elsewhere', ['file_id' => $file->id, 'stage' => $stage->value, 'run_id' => $runId]);

            return null;
        }

        if ($stage->isCore()) {
            $file->update(['ocr_status' => FileOcrStatus::Processing, 'ocr_failure_reason' => null]);
        }

        return $run;
    }

    /** A stage ran to the end: record it, then carry on with the next stage. */
    public function finished(FileOcrStageRun $run, StageOutcome $outcome): void
    {
        $file = File::query()->findOrFail($run->file_id);
        $run->update([
            'status' => $outcome->status,
            'reason' => $outcome->reason,
            'summary' => $outcome->summary === [] ? null : $outcome->summary,
            'error' => null,
            'finished_at' => now(),
            'input_fingerprint' => $outcome->status === FileOcrStageRun::STATUS_SUCCEEDED ? $this->fingerprints->for($run->stage, $file) : null,
        ]);

        Log::info('OCR stage finished', [
            'file_id' => $file->id, 'stage' => $run->stage->value, 'status' => $outcome->status, 'reason' => $outcome->reason,
            'attempts' => $run->attempts, 'summary' => $outcome->summary,
        ]);

        $this->continueFrom($file, $run->stage->next(), (string) $run->run_id, false, $run->stage->isCore());
    }

    /**
     * A stage attempt threw. With $retryInSeconds the job is being released
     * to try again; without, this was its last attempt.
     */
    public function attemptFailed(FileOcrStageRun $run, Throwable $e, ?int $retryInSeconds): void
    {
        if ($retryInSeconds === null) {
            $this->recordFailure($run, $e);

            return;
        }

        $run->update(['status' => FileOcrStageRun::STATUS_QUEUED, 'reason' => FileOcrStageRun::REASON_RETRYING, 'error' => $this->errorText($e), 'queued_at' => now()]);
        Log::warning('OCR stage attempt failed; retrying', [
            'file_id' => $run->file_id, 'stage' => $run->stage->value, 'attempt' => $run->attempts, 'retry_in_seconds' => $retryInSeconds, 'error' => $e->getMessage(),
        ]);
    }

    /**
     * The queue gave up on a stage job — after its last attempt, on a
     * timeout, or when it was failed on purpose. Failures already recorded
     * are left alone.
     */
    public function abandoned(int $fileId, OcrStage $stage, ?string $runId, ?Throwable $e): void
    {
        $run = FileOcrStageRun::query()->where('file_id', $fileId)->where('stage', $stage->value)->first();
        if ($run === null || ($runId !== null && $run->run_id !== $runId)) {
            return;
        }
        if (! in_array($run->status, [FileOcrStageRun::STATUS_QUEUED, FileOcrStageRun::STATUS_RUNNING], true)) {
            return;
        }
        // A redelivery that ran out of attempts while its first delivery may still be working
        // (queue retry_after shorter than the stage's timeout): leave it to that worker, or to the stale check.
        if ($e instanceof MaxAttemptsExceededException && ! $e instanceof TimeoutExceededException
            && $run->status === FileOcrStageRun::STATUS_RUNNING && ! $this->isStale($run)) {
            return;
        }

        $this->recordFailure($run, $e ?? new RuntimeException('The queue gave up on this stage.'));
    }

    /**
     * Each stage's state, for the review UI.
     *
     * @return list<array{stage: string, core: bool, status: ?string, reason: ?string, attempts: int, error: ?string, stale: bool, can_run: bool, queued_at: ?string, started_at: ?string, finished_at: ?string, summary: ?array<string, mixed>}>
     */
    public function describe(File $file): array
    {
        $runs = $file->ocrStageRuns()->get()->keyBy(fn (FileOcrStageRun $run) => $run->stage->value);
        $busy = $this->isBusy($file);

        return array_map(function (OcrStage $stage) use ($runs, $busy, $file) {
            /** @var FileOcrStageRun|null $run */
            $run = $runs->get($stage->value);

            return [
                'stage' => $stage->value,
                'core' => $stage->isCore(),
                'status' => $run?->status,
                'reason' => $run?->reason,
                'attempts' => $run->attempts ?? 0,
                'error' => $run?->error,
                'stale' => $run !== null && $this->isStale($run),
                'can_run' => $file->isOcrCandidate() && ! $busy && $this->prerequisitesMet($file, $stage),
                'queued_at' => $run?->queued_at?->toIso8601String(),
                'started_at' => $run?->started_at?->toIso8601String(),
                'finished_at' => $run?->finished_at?->toIso8601String(),
                'summary' => $run?->summary,
            ];
        }, OcrStage::cases());
    }

    /**
     * Walks forward from $stage: skips each stage that's up to date or
     * doesn't apply, and dispatches the first one that must run — which, when
     * it finishes, calls back into finished() to continue.
     *
     * @return self::STARTED|self::UP_TO_DATE|self::BUSY
     */
    private function continueFrom(File $file, ?OcrStage $stage, string $runId, bool $force, bool $coreContext): string
    {
        for (; $stage !== null; $stage = $stage->next(), $force = false) {
            if ($coreContext && ! $stage->isCore()) {
                // Every core stage is done: optional stages see a completed file.
                $this->markCompleted($file);
                $coreContext = false;
            }

            $run = FileOcrStageRun::query()->firstOrNew(['file_id' => $file->id, 'stage' => $stage->value]);
            if ($run->exists && $run->run_id !== $runId && $run->status === FileOcrStageRun::STATUS_RUNNING && ! $this->isStale($run)) {
                return self::BUSY;
            }
            $run->run_id = $runId;

            if (! $force && $this->isUpToDate($run, $stage, $file)) {
                $run->fill(['status' => FileOcrStageRun::STATUS_SKIPPED, 'reason' => FileOcrStageRun::REASON_UP_TO_DATE, 'error' => null])->save();

                continue;
            }

            $refusal = $this->preflight($stage, $file);
            if ($refusal !== null) {
                $run->fill([
                    'status' => FileOcrStageRun::STATUS_SKIPPED, 'reason' => $refusal, 'error' => null, 'summary' => null,
                    'input_fingerprint' => null, 'finished_at' => now(),
                ])->save();

                continue;
            }

            $run->fill([
                'status' => FileOcrStageRun::STATUS_QUEUED, 'reason' => null, 'error' => null, 'attempts' => 0,
                'queued_at' => now(), 'started_at' => null, 'finished_at' => null,
            ])->save();
            if ($stage->isCore() && $file->ocr_status !== FileOcrStatus::Processing) {
                $file->update([
                    'ocr_status' => FileOcrStatus::Pending, 'ocr_failure_reason' => null,
                    ...($stage === OcrStage::Recognize ? ['ocr_progress_pct' => 0] : []),
                ]);
            }
            dispatch($this->jobFor($stage, $file->id, $runId));

            return self::STARTED;
        }

        if ($coreContext) {
            $this->markCompleted($file);
        }

        return self::UP_TO_DATE;
    }

    private function isUpToDate(?FileOcrStageRun $run, OcrStage $stage, File $file): bool
    {
        if ($run?->input_fingerprint === null) {
            return false;
        }

        try {
            return hash_equals($run->input_fingerprint, $this->fingerprints->for($stage, $file));
        } catch (InvalidArgumentException) {
            return false; // a misconfigured provider; preflight says why
        }
    }

    /** Why a stage can't apply to this file right now — checked before queueing it, so no job is queued for nothing. */
    private function preflight(OcrStage $stage, File $file): ?string
    {
        if ($stage === OcrStage::MatchEntities) {
            return app(EntityMatchingService::class)->mentions($file) === [] ? 'nothing_to_match' : null;
        }
        if ($stage !== OcrStage::Correct) {
            return null;
        }

        try {
            return (new CorrectionPolicy)->refusalFor($file, app(OcrCorrectionProvider::class));
        } catch (InvalidArgumentException $e) {
            Log::error('OCR correction provider is misconfigured', ['error' => $e->getMessage()]);

            return 'provider_misconfigured';
        }
    }

    private function recordFailure(FileOcrStageRun $run, Throwable $e): void
    {
        $run->update(['status' => FileOcrStageRun::STATUS_FAILED, 'reason' => null, 'error' => $this->errorText($e), 'finished_at' => now()]);
        Log::error('OCR stage failed', ['file_id' => $run->file_id, 'stage' => $run->stage->value, 'attempts' => $run->attempts, 'exception' => $e]);

        $file = File::query()->find($run->file_id);
        if ($file === null) {
            return;
        }

        if ($run->stage->isCore()) {
            $file->update(['ocr_status' => FileOcrStatus::Failed, 'ocr_failure_reason' => mb_substr($e->getMessage(), 0, 2000)]);
            FileOcrStageRun::query()
                ->where('file_id', $file->id)->where('run_id', $run->run_id)->where('status', FileOcrStageRun::STATUS_PENDING)
                ->update(['status' => FileOcrStageRun::STATUS_SKIPPED, 'reason' => FileOcrStageRun::REASON_UPSTREAM_FAILED]);

            return;
        }

        // An optional stage failing doesn't hold up the stages after it.
        $this->continueFrom($file, $run->stage->next(), (string) $run->run_id, false, false);
    }

    private function markCompleted(File $file): void
    {
        $file->refresh();
        if ($file->ocr_status !== FileOcrStatus::Completed) {
            $file->update(['ocr_status' => FileOcrStatus::Completed, 'ocr_progress_pct' => 100, 'ocr_completed_at' => now(), 'ocr_failure_reason' => null]);
        }
    }

    private function isStale(FileOcrStageRun $run): bool
    {
        return match ($run->status) {
            FileOcrStageRun::STATUS_RUNNING => $run->started_at === null
                || $run->started_at->lt(now()->subSeconds($this->timeoutFor($run->stage) + self::RUNNING_GRACE_SECONDS)),
            // Lost from the queue (flushed, or its job was never picked up).
            FileOcrStageRun::STATUS_QUEUED => $run->queued_at === null
                || $run->queued_at->lt(now()->subMinutes($this->queuedStaleMinutes())),
            default => false,
        };
    }

    private function timeoutFor(OcrStage $stage): int
    {
        return (int) config("ocr.pipeline.timeouts.{$stage->value}", 1800);
    }

    private function queuedStaleMinutes(): int
    {
        return (int) config('ocr.pipeline.queued_stale_after_minutes', 60);
    }

    private function runFor(File $file, OcrStage $stage): ?FileOcrStageRun
    {
        return FileOcrStageRun::query()->where('file_id', $file->id)->where('stage', $stage->value)->first();
    }

    private function errorText(Throwable $e): string
    {
        return mb_substr(class_basename($e).': '.$e->getMessage(), 0, 2000);
    }

    private function jobFor(OcrStage $stage, int $fileId, string $runId): ShouldQueue
    {
        return match ($stage) {
            OcrStage::Recognize => new ProcessFileOcrJob($fileId, $runId),
            OcrStage::Extract => new ExtractOcrFieldsJob($fileId, $runId),
            OcrStage::MatchEntities => new MatchEntitiesJob($fileId, $runId),
            OcrStage::Correct => new CorrectOcrJob($fileId, $runId),
        };
    }
}
