<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photograph of an artwork, stored on the private disk and streamed through the API. */
class ArtworkImage extends Model
{
    use LogsChanges;

    public const RIGHTS = ['unknown', 'licensed', 'public_domain', 'all_rights_reserved'];

    public const CLEAR_RIGHTS = ['licensed', 'public_domain'];

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_final' => 'boolean', 'size_bytes' => 'integer', 'width_px' => 'integer', 'height_px' => 'integer'];
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function isClearForPublic(): bool
    {
        return in_array($this->rights_status, self::CLEAR_RIGHTS, true);
    }

    public function activitySubjectLabel(): string
    {
        return 'Artwork #'.$this->artwork_id.' image: '.($this->original_filename ?? $this->getKey());
    }

    /**
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['path', 'sha256'];
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'rights_status' => ['ar' => 'حالة الحقوق', 'en' => 'Rights status'],
            'is_final' => ['ar' => 'الصورة النهائية', 'en' => 'Final image'],
            'original_filename' => ['ar' => 'اسم الملف الأصلي', 'en' => 'Original filename'],
        ];
    }
}
