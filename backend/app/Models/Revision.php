<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * D73: one row per applied change, whether a direct edit, an approved
 * proposal or a rollback. A coarser, record-scoped, revertible layer on top
 * of LogsChanges — never a replacement for it.
 */
class Revision extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_diffs' => 'array',
            'applied_at' => 'datetime',
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
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    /**
     * @return BelongsTo<EditProposal, $this>
     */
    public function editProposal(): BelongsTo
    {
        return $this->belongsTo(EditProposal::class);
    }
}
