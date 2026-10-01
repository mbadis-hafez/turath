<?php

namespace App\Models;

use App\Enums\OcrRegionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One geometric region detected on one OCR'd page, classified before any OCR
 * text from it is trusted. Logos and noise never reach the OCR/correction
 * layers; handwriting and signatures are cropped for manual review instead of
 * being OCR'd. See App\Support\Ocr\RegionClassifier.
 */
class FileOcrRegion extends Model
{
    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'region_type' => OcrRegionType::class,
            'bbox' => 'array',
            'confidence' => 'integer',
            'ocr_allowed' => 'boolean',
            'ai_correction_allowed' => 'boolean',
            'requires_human_review' => 'boolean',
            'has_correction_mark' => 'boolean',
            'correction_marks' => 'array',
            'review_dismissed_at' => 'datetime',
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
     * The AI-corrected layers of this region's OCR text, one per model/prompt
     * version it was corrected with. source_text itself is never modified.
     *
     * @return HasMany<FileOcrRegionCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(FileOcrRegionCorrection::class, 'region_id');
    }
}
