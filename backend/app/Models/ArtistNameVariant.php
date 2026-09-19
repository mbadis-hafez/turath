<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistNameVariant extends Model
{
    use LogsChanges;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'artist_id' => ['ar' => 'الفنان', 'en' => 'Artist'],
            'name' => ['ar' => 'الاسم', 'en' => 'Name'],
            'language' => ['ar' => 'اللغة', 'en' => 'Language'],
            'type' => ['ar' => 'النوع', 'en' => 'Type'],
            'source_note' => ['ar' => 'ملاحظة المصدر', 'en' => 'Source note'],
        ];
    }

    public function activitySubjectLabel(): string
    {
        return $this->name;
    }

    protected static function booted(): void
    {
        static::observe(Observers\ArtistNameVariantObserver::class);
    }
}
