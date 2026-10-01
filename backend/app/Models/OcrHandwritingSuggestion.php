<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A handwriting provider's reading of one crop — a suggestion, never a
 * verified value — plus the reviewer's decision about it. See the migration
 * for why it is keyed by crop content rather than by region.
 */
class OcrHandwritingSuggestion extends Model
{
    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_EMPTY = 'empty';

    public const DECISION_ACCEPTED = 'accepted';

    public const DECISION_EDITED = 'edited';

    public const DECISION_REJECTED = 'rejected';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'raw_result' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<FileOcrRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'region_id');
    }
}
