<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\ArchiveItemLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ArchiveItemLink extends Model
{
    /** @use HasFactory<ArchiveItemLinkFactory> */
    use HasFactory, LogsChanges;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function archiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function activitySubjectLabel(): string
    {
        return 'Archive link #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'role' => ['ar' => 'نوع الارتباط', 'en' => 'Link role'],
            'linkable_type' => ['ar' => 'نوع العنصر المرتبط', 'en' => 'Linked entity type'],
            'linkable_id' => ['ar' => 'معرّف العنصر المرتبط', 'en' => 'Linked entity id'],
        ];
    }

    protected static function booted(): void
    {
        static::observe(Observers\ArchiveItemLinkObserver::class);
    }
}
