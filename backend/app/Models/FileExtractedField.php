<?php

namespace App\Models;

use App\Enums\ExtractedFieldStatus;
use App\Enums\ExtractionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One candidate field value, machine-extracted from a file's OCR text. Stays a
 * suggestion — see docs/privacy-rules.md:10 — until a reviewer accepts it, at
 * which point its value is merged into the archive item's record (directly,
 * or into the reviewer's editorial draft) and this row is stamped accepted.
 */
class FileExtractedField extends Model
{
    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'source_page' => 'integer',
            'status' => ExtractedFieldStatus::class,
            'reviewed_at' => 'datetime',
            'extraction_method' => ExtractionMethod::class,
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
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return BelongsTo<FileOcrRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'region_id');
    }
}
