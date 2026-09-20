<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Database\Factories\ImportMappingProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportMappingProfile extends Model
{
    /** @use HasFactory<ImportMappingProfileFactory> */
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
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function activitySubjectLabel(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'entity_type' => ['ar' => 'نوع الكيان', 'en' => 'Entity type'],
            'name' => ['ar' => 'الاسم', 'en' => 'Name'],
            'column_map' => ['ar' => 'خريطة الأعمدة', 'en' => 'Column map'],
        ];
    }
}
