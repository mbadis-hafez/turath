<?php

namespace App\Models;

use App\Enums\OcrStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one OCR pipeline stage stands for one file. See the migration for how
 * the fingerprint and run id make re-running safe.
 */
class FileOcrStageRun extends Model
{
    /** Part of a started pipeline run, waiting for the stages before it. */
    public const STATUS_PENDING = 'pending';

    /** Dispatched, or waiting to be retried after a failed attempt. */
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    /** Did not run: its input was unchanged, it does not apply, or a stage before it failed — see reason. */
    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const REASON_UP_TO_DATE = 'up_to_date';

    public const REASON_UPSTREAM_FAILED = 'upstream_failed';

    public const REASON_RETRYING = 'retrying';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => OcrStage::class,
            'attempts' => 'integer',
            'summary' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
