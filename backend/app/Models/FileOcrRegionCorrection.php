<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The AI-corrected layer of one printed-text region. The region's own
 * source_text (the OCR layer) is never modified; this is a suggestion beside
 * it, and `needs_review` false only means no check objected — it still isn't
 * a verified value.
 */
class FileOcrRegionCorrection extends Model
{
    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_CORRECTED = 'corrected';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    /** The model's output couldn't be used at all (invalid, or it broke a protected placeholder). */
    public const STATUS_REJECTED = 'rejected';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'review_reasons' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FileOcrRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'region_id');
    }

    /**
     * @return BelongsTo<OcrCorrection, $this>
     */
    public function ocrCorrection(): BelongsTo
    {
        return $this->belongsTo(OcrCorrection::class);
    }
}
