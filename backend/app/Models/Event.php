<?php

namespace App\Models;

use App\Casts\PartialDateCast;
use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A historical event (exhibition, symposium, award…) that Bidayaat documents. */
class Event extends Model
{
    use LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => PartialDateCast::class.':start',
            'end' => PartialDateCast::class.':end',
        ];
    }

    /**
     * @return BelongsTo<Holder, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Holder::class);
    }

    /**
     * @return HasMany<EventParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return MorphToMany<Theme, $this>
     */
    public function themes(): MorphToMany
    {
        return $this->morphToMany(Theme::class, 'taggable', 'theme_taggables');
    }

    /**
     * Search column is machine-maintained noise, never audited.
     *
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['search_text'];
    }

    public function activitySubjectLabel(): string
    {
        return 'Event: '.($this->title_en ?? $this->title_ar ?? $this->getKey());
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'event_type' => ['ar' => 'نوع الفعالية', 'en' => 'Event type'],
            'title_ar' => ['ar' => 'العنوان (عربي)', 'en' => 'Title (Arabic)'],
            'title_en' => ['ar' => 'العنوان (إنجليزي)', 'en' => 'Title (English)'],
            'description_ar' => ['ar' => 'الوصف (عربي)', 'en' => 'Description (Arabic)'],
            'description_en' => ['ar' => 'الوصف (إنجليزي)', 'en' => 'Description (English)'],
            'venue_name' => ['ar' => 'المكان', 'en' => 'Venue'],
            'city' => ['ar' => 'المدينة', 'en' => 'City'],
            'holder_id' => ['ar' => 'الجهة المستضيفة', 'en' => 'Host institution'],
            'start_date_display' => ['ar' => 'تاريخ البداية (نص)', 'en' => 'Start date (display)'],
            'start_year_from' => ['ar' => 'سنة البداية (من)', 'en' => 'Start year (from)'],
            'start_year_to' => ['ar' => 'سنة البداية (إلى)', 'en' => 'Start year (to)'],
            'start_calendar' => ['ar' => 'تقويم البداية', 'en' => 'Start calendar'],
            'start_certainty' => ['ar' => 'دقة تاريخ البداية', 'en' => 'Start date certainty'],
            'end_date_display' => ['ar' => 'تاريخ النهاية (نص)', 'en' => 'End date (display)'],
            'end_year_from' => ['ar' => 'سنة النهاية (من)', 'en' => 'End year (from)'],
            'end_year_to' => ['ar' => 'سنة النهاية (إلى)', 'en' => 'End year (to)'],
            'date_note' => ['ar' => 'مبرر تقريب التاريخ', 'en' => 'Date approximation reason'],
            'publication_status' => ['ar' => 'حالة النشر', 'en' => 'Publication status'],
            'access_level' => ['ar' => 'مستوى الإتاحة', 'en' => 'Access level'],
        ];
    }

    protected static function booted(): void
    {
        static::observe(Observers\EventObserver::class);
    }
}
