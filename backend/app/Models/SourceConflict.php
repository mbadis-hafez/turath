<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\SourceConflictFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SourceConflict extends Model
{
    /** @use HasFactory<SourceConflictFactory> */
    use HasFactory, HasUuids, LogsChanges;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citation_ids' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function citable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function resolvedSource(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'resolved_source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function activitySubjectLabel(): string
    {
        return "Source conflict on {$this->citable_type} #{$this->citable_id} ({$this->field_key})";
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'status' => ['ar' => 'الحالة', 'en' => 'Status'],
            'resolved_source_id' => ['ar' => 'المصدر المعتمد', 'en' => 'Resolved source'],
            'resolution_note' => ['ar' => 'ملاحظة الحسم', 'en' => 'Resolution note'],
        ];
    }
}
