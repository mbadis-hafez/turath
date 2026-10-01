<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A completed AI correction call and its cache entry. Holds masked text only;
 * see the migration. `model_confidence` is the model's own estimate, never a
 * calibrated probability.
 */
class OcrCorrection extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID_OUTPUT = 'invalid_output';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'name_candidates' => 'array',
            'model_confidence' => 'float',
            'model_needs_review' => 'boolean',
            'guard_flags' => 'array',
            'raw_response' => 'array',
            'input_chars' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
