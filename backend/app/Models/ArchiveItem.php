<?php

namespace App\Models;

use App\Casts\PartialDateCast;
use App\Concerns\LogsChanges;
use Database\Factories\ArchiveItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchiveItem extends Model
{
    /** @use HasFactory<ArchiveItemFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => PartialDateCast::class.':content',
            'digitized_at' => 'date',
            'embargo_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ArchiveItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<File, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * @return HasMany<ArchiveItemLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(ArchiveItemLink::class);
    }

    /**
     * Search columns and internal/rights fields are either machine-maintained
     * or curatorial-only; they're excluded from the model's own diff purely
     * for search_text (noise), while internal_notes/rights_holder/consent
     * status DO get audited (F3 spec: hidden publicly, but must still show
     * in the activity log for archive.manage/activity.view holders).
     *
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['search_text'];
    }

    public function activitySubjectLabel(): string
    {
        $label = trim(($this->title_en ?? '').' / '.($this->title_ar ?? ''), ' /');

        return $label !== '' ? $label : 'Archive item #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'legacy_ref' => ['ar' => 'المرجع القديم', 'en' => 'Legacy reference'],
            'parent_id' => ['ar' => 'العنصر الأب', 'en' => 'Parent item'],
            'item_type' => ['ar' => 'نوع المادة', 'en' => 'Item type'],
            'title_ar' => ['ar' => 'العنوان (عربي)', 'en' => 'Title (Arabic)'],
            'title_en' => ['ar' => 'العنوان (إنجليزي)', 'en' => 'Title (English)'],
            'description_ar' => ['ar' => 'الوصف (عربي)', 'en' => 'Description (Arabic)'],
            'description_en' => ['ar' => 'الوصف (إنجليزي)', 'en' => 'Description (English)'],
            'internal_notes' => ['ar' => 'ملاحظات داخلية', 'en' => 'Internal notes'],
            'creator_name' => ['ar' => 'اسم المُنشئ', 'en' => 'Creator name'],
            'publication_name_ar' => ['ar' => 'اسم المصدر (عربي)', 'en' => 'Publication name (Arabic)'],
            'publication_name_en' => ['ar' => 'اسم المصدر (إنجليزي)', 'en' => 'Publication name (English)'],
            'issue_no' => ['ar' => 'رقم العدد', 'en' => 'Issue number'],
            'page' => ['ar' => 'رقم الصفحة', 'en' => 'Page'],
            'language' => ['ar' => 'اللغة', 'en' => 'Language'],
            'original_format' => ['ar' => 'الشكل الأصلي', 'en' => 'Original format'],
            'source_filename' => ['ar' => 'اسم الملف عند الرقمنة', 'en' => 'Source filename'],
            'quality_flag' => ['ar' => 'مستوى الجودة', 'en' => 'Quality flag'],
            'content_date_display' => ['ar' => 'تاريخ المحتوى (نص)', 'en' => 'Content date (display)'],
            'content_year_from' => ['ar' => 'سنة المحتوى (من)', 'en' => 'Content year (from)'],
            'content_year_to' => ['ar' => 'سنة المحتوى (إلى)', 'en' => 'Content year (to)'],
            'content_calendar' => ['ar' => 'تقويم المحتوى', 'en' => 'Content calendar'],
            'content_certainty' => ['ar' => 'دقة تاريخ المحتوى', 'en' => 'Content date certainty'],
            'digitized_at' => ['ar' => 'تاريخ الرقمنة', 'en' => 'Digitized at'],
            'access_level' => ['ar' => 'مستوى الإتاحة', 'en' => 'Access level'],
            'embargo_until' => ['ar' => 'تاريخ انتهاء الحظر', 'en' => 'Embargo until'],
            'post_embargo_access_level' => ['ar' => 'مستوى الإتاحة بعد انتهاء الحظر', 'en' => 'Post-embargo access level'],
            'rights_status' => ['ar' => 'حالة الحقوق', 'en' => 'Rights status'],
            'rights_holder_ar' => ['ar' => 'مالك الحقوق (عربي)', 'en' => 'Rights holder (Arabic)'],
            'rights_holder_en' => ['ar' => 'مالك الحقوق (إنجليزي)', 'en' => 'Rights holder (English)'],
            'license' => ['ar' => 'الترخيص', 'en' => 'License'],
            'consent_status' => ['ar' => 'حالة الموافقة', 'en' => 'Consent status'],
            'publication_status' => ['ar' => 'حالة النشر', 'en' => 'Publication status'],
        ];
    }

    protected static function booted(): void
    {
        static::observe(Observers\ArchiveItemObserver::class);
    }
}
