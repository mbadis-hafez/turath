<?php

namespace App\Models;

use Database\Factories\RecordCompletenessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Materialized, per D53 — the CompletenessCalculator writes rows via the
 * query builder (`updateOrInsert`), not Eloquent's save(), because this
 * table has a composite primary key (citable_type, citable_id) that plain
 * Eloquent's single-column key assumptions don't support.
 */
class RecordCompleteness extends Model
{
    /** @use HasFactory<RecordCompletenessFactory> */
    use HasFactory;

    protected $table = 'record_completeness';

    public $timestamps = false;

    public $incrementing = false;

    /** Not a real single-column key (the true key is the citable_type+citable_id pair) — set so Eloquent's internals don't crash, but never rely on find()/getKey() identity here. */
    protected $primaryKey = 'citable_id';

    protected $keyType = 'int';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocking_gap_field_keys' => 'array',
            'minor_gap_field_keys' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function citable(): MorphTo
    {
        return $this->morphTo();
    }
}
