<?php

namespace App\Models;

use App\Enums\DateCalendar;
use App\Enums\ExtractedDateType;
use App\Enums\ExtractedFieldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A structured date found in a document, kept in its original calendar and
 * with its semantic role preserved — a document's Hijri issue date, its
 * Gregorian equivalent, and a handwritten signature date are three distinct
 * facts, not one date normalized three ways. See App\Support\Ocr\DateExtractor.
 */
class FileExtractedDate extends Model
{
    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calendar' => DateCalendar::class,
            'date_type' => ExtractedDateType::class,
            'source_page' => 'integer',
            'confidence' => 'integer',
            'status' => ExtractedFieldStatus::class,
            'reviewed_at' => 'datetime',
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

    protected static function booted(): void
    {
        // Decisions become evaluation/training examples (Dataset\ReviewExampleRecorder).
        static::observe(Observers\RecordsOcrReviewDecisions::class);
    }
}
