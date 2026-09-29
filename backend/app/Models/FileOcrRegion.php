<?php

namespace App\Models;

use App\Enums\OcrRegionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
