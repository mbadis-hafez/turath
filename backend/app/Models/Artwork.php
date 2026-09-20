<?php

namespace App\Models;

use App\Casts\PartialDateCast;
use App\Concerns\LogsChanges;
use Database\Factories\ArtworkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artwork extends Model
{
    /** @use HasFactory<ArtworkFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'creation' => PartialDateCast::class.':creation',
            'is_untitled' => 'boolean',
            'height_cm' => 'float',
            'width_cm' => 'float',
            'depth_cm' => 'float',
            'frame_height_cm' => 'float',
            'frame_width_cm' => 'float',
            'frame_depth_cm' => 'float',
            'weight_kg' => 'float',
            'edition_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * @return BelongsTo<Holder, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class);
    }

    /**
     * Search columns are machine-maintained noise, never audited.
     *
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['search_text'];
    }

    public function activitySubjectLabel(): string
    {
        if ($this->is_untitled) {
            return 'Artwork #'.$this->getKey().' (untitled)';
        }

        $label = trim(($this->title_en ?? '').' / '.($this->title_ar ?? ''), ' /');

        return $label !== '' ? $label : 'Artwork #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'legacy_ref' => ['ar' => 'المرجع القديم', 'en' => 'Legacy reference'],
            'artist_id' => ['ar' => 'الفنان', 'en' => 'Artist'],
            'attribution_certainty' => ['ar' => 'درجة النسب', 'en' => 'Attribution certainty'],
            'title_ar' => ['ar' => 'العنوان (عربي)', 'en' => 'Title (Arabic)'],
            'title_en' => ['ar' => 'العنوان (إنجليزي)', 'en' => 'Title (English)'],
            'is_untitled' => ['ar' => 'بدون عنوان', 'en' => 'Untitled'],
            'category' => ['ar' => 'النوع', 'en' => 'Category'],
            'medium_ar' => ['ar' => 'الخامة (عربي)', 'en' => 'Medium (Arabic)'],
            'medium_en' => ['ar' => 'الخامة (إنجليزي)', 'en' => 'Medium (English)'],
            'edition_number' => ['ar' => 'رقم النسخة', 'en' => 'Edition number'],
            'edition_size' => ['ar' => 'عدد النسخ', 'en' => 'Edition size'],
            'height_cm' => ['ar' => 'الارتفاع (سم)', 'en' => 'Height (cm)'],
            'width_cm' => ['ar' => 'العرض (سم)', 'en' => 'Width (cm)'],
            'depth_cm' => ['ar' => 'العمق (سم)', 'en' => 'Depth (cm)'],
            'frame_height_cm' => ['ar' => 'ارتفاع الإطار (سم)', 'en' => 'Frame height (cm)'],
            'frame_width_cm' => ['ar' => 'عرض الإطار (سم)', 'en' => 'Frame width (cm)'],
            'frame_depth_cm' => ['ar' => 'عمق الإطار (سم)', 'en' => 'Frame depth (cm)'],
            'dimensions_raw' => ['ar' => 'الأبعاد (نص أصلي)', 'en' => 'Dimensions (raw)'],
            'frame_dimensions_raw' => ['ar' => 'أبعاد الإطار (نص أصلي)', 'en' => 'Frame dimensions (raw)'],
            'weight_kg' => ['ar' => 'الوزن (كجم)', 'en' => 'Weight (kg)'],
            'signed' => ['ar' => 'التوقيع', 'en' => 'Signed'],
            'creation_date_display' => ['ar' => 'تاريخ الإنجاز (نص)', 'en' => 'Creation date (display)'],
            'creation_year_from' => ['ar' => 'سنة الإنجاز (من)', 'en' => 'Creation year (from)'],
            'creation_year_to' => ['ar' => 'سنة الإنجاز (إلى)', 'en' => 'Creation year (to)'],
            'creation_calendar' => ['ar' => 'تقويم الإنجاز', 'en' => 'Creation calendar'],
            'creation_certainty' => ['ar' => 'دقة تاريخ الإنجاز', 'en' => 'Creation date certainty'],
            'holder_id' => ['ar' => 'الجهة الحائزة', 'en' => 'Holder'],
            'holder_inventory_no' => ['ar' => 'رقم جرد الحائز', 'en' => 'Holder inventory number'],
            'notes_ar' => ['ar' => 'ملاحظات (عربي)', 'en' => 'Notes (Arabic)'],
            'notes_en' => ['ar' => 'ملاحظات (إنجليزي)', 'en' => 'Notes (English)'],
            'publication_status' => ['ar' => 'حالة النشر', 'en' => 'Publication status'],
        ];
    }

    protected static function booted(): void
    {
        static::observe(Observers\ArtworkObserver::class);
    }
}
