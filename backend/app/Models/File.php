<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use App\Enums\DocumentType;
use App\Enums\FileOcrStatus;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width_px' => 'integer',
            'height_px' => 'integer',
            'duration_seconds' => 'integer',
            'ocr_status' => FileOcrStatus::class,
            'ocr_progress_pct' => 'integer',
            'ocr_completed_at' => 'datetime',
            'ocr_language_confidence' => 'array',
            'document_type' => DocumentType::class,
        ];
    }

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function archiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<FileExtractedText, $this>
     */
    public function extractedTexts(): HasMany
    {
        return $this->hasMany(FileExtractedText::class);
    }

    /**
     * @return HasMany<FileExtractedField, $this>
     */
    public function extractedFields(): HasMany
    {
        return $this->hasMany(FileExtractedField::class);
    }

    /**
     * @return HasMany<FileOcrRegion, $this>
     */
    public function ocrRegions(): HasMany
    {
        return $this->hasMany(FileOcrRegion::class);
    }

    /**
     * @return HasMany<FileOcrFormField, $this>
     */
    public function ocrFormFields(): HasMany
    {
        return $this->hasMany(FileOcrFormField::class);
    }

    /**
     * @return HasMany<FileExtractedDate, $this>
     */
    public function extractedDates(): HasMany
    {
        return $this->hasMany(FileExtractedDate::class);
    }

    /** OCR only makes sense for images and PDFs — not video, audio, or opaque office formats. */
    public function isOcrCandidate(): bool
    {
        return $this->mime_type === 'application/pdf' || str_starts_with($this->mime_type, 'image/');
    }

    public function activitySubjectLabel(): string
    {
        return 'File #'.$this->getKey().' ('.$this->role.')';
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'role' => ['ar' => 'الدور', 'en' => 'Role'],
            'original_filename' => ['ar' => 'اسم الملف الأصلي', 'en' => 'Original filename'],
            'mime_type' => ['ar' => 'نوع الملف', 'en' => 'MIME type'],
            'size_bytes' => ['ar' => 'الحجم (بايت)', 'en' => 'Size (bytes)'],
            'sha256' => ['ar' => 'بصمة SHA-256', 'en' => 'SHA-256 checksum'],
        ];
    }
}
