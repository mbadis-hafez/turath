<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Education, award, exhibition, talk or symposium line on an artist's profile. */
class ArtistEntry extends Model
{
    use LogsChanges;

    public const TYPES = ['education', 'award', 'exhibition', 'talk', 'symposium'];

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function activitySubjectLabel(): string
    {
        return ucfirst($this->type).': '.($this->title_en ?? $this->title_ar ?? $this->getKey());
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'title_ar' => ['ar' => 'العنوان (عربي)', 'en' => 'Title (Arabic)'],
            'title_en' => ['ar' => 'العنوان (إنجليزي)', 'en' => 'Title (English)'],
            'place_ar' => ['ar' => 'الجهة (عربي)', 'en' => 'Place (Arabic)'],
            'place_en' => ['ar' => 'الجهة (إنجليزي)', 'en' => 'Place (English)'],
            'year_from' => ['ar' => 'من سنة', 'en' => 'Year from'],
            'year_to' => ['ar' => 'إلى سنة', 'en' => 'Year to'],
        ];
    }
}
