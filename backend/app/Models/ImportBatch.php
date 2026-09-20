<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory, HasUuids, LogsChanges, SoftDeletes;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'validated_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportBatchRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class);
    }

    /**
     * @return BelongsTo<ImportMappingProfile, $this>
     */
    public function mappingProfile(): BelongsTo
    {
        return $this->belongsTo(ImportMappingProfile::class, 'mapping_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function activitySubjectLabel(): string
    {
        return "Import batch #{$this->getKey()} ({$this->entity_type}, {$this->original_filename})";
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'status' => ['ar' => 'الحالة', 'en' => 'Status'],
            'row_count' => ['ar' => 'عدد الصفوف', 'en' => 'Row count'],
            'new_count' => ['ar' => 'عدد الصفوف الجديدة', 'en' => 'New count'],
            'matched_count' => ['ar' => 'عدد الصفوف المطابقة', 'en' => 'Matched count'],
            'error_count' => ['ar' => 'عدد الأخطاء', 'en' => 'Error count'],
            'skipped_count' => ['ar' => 'عدد الصفوف المتجاوزة', 'en' => 'Skipped count'],
            'validated_at' => ['ar' => 'تاريخ التحقق', 'en' => 'Validated at'],
            'committed_at' => ['ar' => 'تاريخ الاعتماد', 'en' => 'Committed at'],
        ];
    }
}
