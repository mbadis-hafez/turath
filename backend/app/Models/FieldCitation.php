<?php

namespace App\Models;

use Database\Factories\FieldCitationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A citation is a record of what happened (which source asserted which
 * value for which field), not an editable entity — like ImportBatchRow, it
 * has no LogsChanges of its own; it's created and deleted, not edited.
 */
class FieldCitation extends Model
{
    /** @use HasFactory<FieldCitationFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_value' => 'array',
            'is_primary' => 'boolean',
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
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
