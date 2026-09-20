<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\HolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holder extends Model
{
    /** @use HasFactory<HolderFactory> */
    use HasFactory, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public_name' => 'boolean',
            'is_estate' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Artwork, $this>
     */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    public function activitySubjectLabel(): string
    {
        $label = trim(($this->name_en ?? '').' / '.($this->name_ar ?? ''), ' /');

        return $label !== '' ? $label : 'Holder #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'legacy_code' => ['ar' => 'الرمز القديم', 'en' => 'Legacy code'],
            'type' => ['ar' => 'النوع', 'en' => 'Type'],
            'name_ar' => ['ar' => 'الاسم (عربي)', 'en' => 'Name (Arabic)'],
            'name_en' => ['ar' => 'الاسم (إنجليزي)', 'en' => 'Name (English)'],
            'city_ar' => ['ar' => 'المدينة (عربي)', 'en' => 'City (Arabic)'],
            'city_en' => ['ar' => 'المدينة (إنجليزي)', 'en' => 'City (English)'],
            'country_ar' => ['ar' => 'الدولة (عربي)', 'en' => 'Country (Arabic)'],
            'country_en' => ['ar' => 'الدولة (إنجليزي)', 'en' => 'Country (English)'],
            'is_public_name' => ['ar' => 'إظهار الاسم للعامة', 'en' => 'Public name'],
            'is_estate' => ['ar' => 'تركة الفنان', 'en' => 'Is estate'],
            'internal_notes' => ['ar' => 'ملاحظات داخلية', 'en' => 'Internal notes'],
        ];
    }
}
