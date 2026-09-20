<?php

namespace App\Models;

use App\Casts\PartialDateCast;
use App\Concerns\LogsChanges;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth' => PartialDateCast::class.':birth',
            'death' => PartialDateCast::class.':death',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ArtistNameVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ArtistNameVariant::class);
    }

    /**
     * @return HasMany<Artwork, $this>
     */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Search columns are machine-maintained noise, never audited.
     *
     * @return array<int, string>
     */
    public function excludedFromActivityLog(): array
    {
        return ['search_text', 'search_compact'];
    }

    public function activitySubjectLabel(): string
    {
        $label = trim(($this->name_en ?? '').' / '.($this->name_ar ?? ''), ' /');

        return $label !== '' ? $label : 'Artist #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'legacy_code' => ['ar' => 'الرمز القديم', 'en' => 'Legacy code'],
            'slug' => ['ar' => 'الاسم المختصر (slug)', 'en' => 'Slug'],
            'name_ar' => ['ar' => 'الاسم (عربي)', 'en' => 'Name (Arabic)'],
            'name_en' => ['ar' => 'الاسم (إنجليزي)', 'en' => 'Name (English)'],
            'bio_ar' => ['ar' => 'السيرة (عربي)', 'en' => 'Biography (Arabic)'],
            'bio_en' => ['ar' => 'السيرة (إنجليزي)', 'en' => 'Biography (English)'],
            'birth_date_display' => ['ar' => 'تاريخ الميلاد (نص)', 'en' => 'Birth date (display)'],
            'birth_year_from' => ['ar' => 'سنة الميلاد (من)', 'en' => 'Birth year (from)'],
            'birth_year_to' => ['ar' => 'سنة الميلاد (إلى)', 'en' => 'Birth year (to)'],
            'birth_calendar' => ['ar' => 'تقويم الميلاد', 'en' => 'Birth calendar'],
            'birth_certainty' => ['ar' => 'دقة تاريخ الميلاد', 'en' => 'Birth date certainty'],
            'birth_place_ar' => ['ar' => 'مكان الميلاد (عربي)', 'en' => 'Birth place (Arabic)'],
            'birth_place_en' => ['ar' => 'مكان الميلاد (إنجليزي)', 'en' => 'Birth place (English)'],
            'death_date_display' => ['ar' => 'تاريخ الوفاة (نص)', 'en' => 'Death date (display)'],
            'death_year_from' => ['ar' => 'سنة الوفاة (من)', 'en' => 'Death year (from)'],
            'death_year_to' => ['ar' => 'سنة الوفاة (إلى)', 'en' => 'Death year (to)'],
            'death_calendar' => ['ar' => 'تقويم الوفاة', 'en' => 'Death calendar'],
            'death_certainty' => ['ar' => 'دقة تاريخ الوفاة', 'en' => 'Death date certainty'],
            'death_place_ar' => ['ar' => 'مكان الوفاة (عربي)', 'en' => 'Death place (Arabic)'],
            'death_place_en' => ['ar' => 'مكان الوفاة (إنجليزي)', 'en' => 'Death place (English)'],
            'living_status' => ['ar' => 'حالة الحياة', 'en' => 'Living status'],
            'verified_status' => ['ar' => 'حالة التوثيق', 'en' => 'Verified status'],
            'verified_by_user_id' => ['ar' => 'تم التوثيق بواسطة', 'en' => 'Verified by'],
            'verified_at' => ['ar' => 'تاريخ التوثيق', 'en' => 'Verified at'],
            'publication_status' => ['ar' => 'حالة النشر', 'en' => 'Publication status'],
        ];
    }

    protected static function booted(): void
    {
        static::observe(Observers\ArtistObserver::class);
    }
}
