<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "label: value" pair detected on a form-like page (e.g. an authorization
 * letter's "اسم الفنان/ة:" line). When the value region is handwriting, the
 * value is never OCR-guessed — a reviewer transcribes it manually via
 * ArchiveItemFileOcrFormFieldController::transcribe.
 */
class FileOcrFormField extends Model
{
    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_manual_transcription' => 'boolean',
            'transcribed_at' => 'datetime',
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
    public function labelRegion(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'label_region_id');
    }

    /**
     * @return BelongsTo<FileOcrRegion, $this>
     */
    public function valueRegion(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'value_region_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function transcribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transcribed_by_user_id');
    }
}
