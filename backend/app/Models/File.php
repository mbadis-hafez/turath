<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
