<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D153: transient staging storage, entirely separate from `files`, so that
 * table's guarantee ("every row belongs to a real, reviewed archive item")
 * stays true. Not audited: there is nothing here worth an independent history.
 */
class MaterialSubmissionFile extends Model
{
    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<MaterialSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(MaterialSubmission::class, 'material_submission_id');
    }
}
