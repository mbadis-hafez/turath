<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * D67: a contributor's change to a record, held as a pending diff until a
 * reviewer approves it. Never itself audited by LogsChanges — the write it
 * eventually causes is what gets audited, through the record's normal path.
 */
class EditProposal extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_diffs' => 'array',
            'proposed_citations' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function citable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return BelongsTo<Revision, $this>
     */
    public function resultingRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'resulting_revision_id');
    }

    /**
     * The column names this proposal touches.
     *
     * @return array<int, string>
     */
    public function fieldKeys(): array
    {
        return array_keys($this->field_diffs ?? []);
    }
}
