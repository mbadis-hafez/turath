<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\ExtractedFieldStatus;
use App\Enums\ExtractionMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One candidate field value, machine-extracted from a file's OCR text. Stays a
 * suggestion — see docs/privacy-rules.md:10 — until a reviewer accepts it.
 * extracted_value is what the machine read and is never overwritten;
 * verified_value is what a reviewer edited or confirmed. Where the field
 * belongs to an archive-item field, accepting merges verified_value into the
 * record (directly, or into the reviewer's editorial draft); document-type
 * fields bound for other records are only verified here — see
 * App\Support\Ocr\Extraction\FieldDefinition for the routes.
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
            'document_type' => DocumentType::class,
            'ordinal' => 'integer',
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

    /**
     * @return BelongsTo<FileOcrFormField, $this>
     */
    public function formField(): BelongsTo
    {
        return $this->belongsTo(FileOcrFormField::class, 'form_field_id');
    }

    /** The value a reviewer would accept: their edit if they made one, otherwise what the machine read. */
    public function currentValue(): ?string
    {
        return $this->verified_value ?? $this->extracted_value;
    }

    protected static function booted(): void
    {
        // Decisions become evaluation/training examples (Dataset\ReviewExampleRecorder).
        static::observe(Observers\RecordsOcrReviewDecisions::class);
    }
}
