<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory, HasUuids, LogsChanges;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return HasMany<FieldCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(FieldCitation::class);
    }

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function linkedArchiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class, 'linked_archive_item_id');
    }

    /**
     * D110/D111: a linked source takes its title and type from the Archive
     * Item itself, so the same document is never classified twice.
     *
     * @return array{ar: string|null, en: string|null}
     */
    public function displayTitle(): array
    {
        $item = $this->linked_archive_item_id !== null ? $this->linkedArchiveItem : null;

        return [
            'ar' => $this->title_ar ?? $item?->title_ar,
            'en' => $this->title_en ?? $item?->title_en,
        ];
    }

    public function effectiveType(): string
    {
        if ($this->linked_archive_item_id === null) {
            return $this->source_type;
        }

        return $this->linkedArchiveItem->item_type ?? $this->source_type;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function activitySubjectLabel(): string
    {
        $label = trim(($this->title_en ?? '').' / '.($this->title_ar ?? ''), ' /');

        return $label !== '' ? $label : 'Source #'.$this->getKey();
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'source_type' => ['ar' => 'نوع المصدر', 'en' => 'Source type'],
            'title_ar' => ['ar' => 'العنوان (عربي)', 'en' => 'Title (Arabic)'],
            'title_en' => ['ar' => 'العنوان (إنجليزي)', 'en' => 'Title (English)'],
            'publisher_or_outlet' => ['ar' => 'الناشر/الجهة', 'en' => 'Publisher/outlet'],
            'reference_note' => ['ar' => 'ملاحظة مرجعية', 'en' => 'Reference note'],
            'url' => ['ar' => 'الرابط', 'en' => 'URL'],
            'year' => ['ar' => 'سنة النشر', 'en' => 'Year'],
        ];
    }
}
