<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtworkPipelineStage extends Model
{
    use HasUuids, LogsChanges;

    public const CREATED_AT = null;

    public const KEYS = ['work_category', 'status_research', 'selection_process', 'availability', 'owner_pre_agreement', 'contract_draft'];

    public const STATUSES = ['not_started', 'in_progress', 'tbc', 'done', 'not_applicable'];

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function isCleared(): bool
    {
        return in_array($this->status, ['done', 'not_applicable'], true);
    }

    public function activitySubjectLabel(): string
    {
        return "Artwork #{$this->artwork_id} pipeline: {$this->stage_key}";
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'status' => ['ar' => 'الحالة', 'en' => 'Status'],
            'note' => ['ar' => 'ملاحظة', 'en' => 'Note'],
            'linked_file_id' => ['ar' => 'الملف المرتبط', 'en' => 'Linked file'],
        ];
    }
}
