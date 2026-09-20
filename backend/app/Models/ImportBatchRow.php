<?php

namespace App\Models;

use Database\Factories\ImportBatchRowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row's outcome is a permanent record of what happened during that
 * import run — it intentionally does not use LogsChanges (it isn't an
 * editable entity, it's the history itself) and is never soft-deleted
 * independently of its batch.
 */
class ImportBatchRow extends Model
{
    /** @use HasFactory<ImportBatchRowFactory> */
    use HasFactory, HasUuids;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'mapped_data' => 'array',
            'validation_errors' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
