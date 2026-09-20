<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CandidateArtwork extends Model
{
    use HasUuids, LogsChanges;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    public function activitySubjectLabel(): string
    {
        return 'Candidate artwork: '.($this->suggested_title_en ?? $this->suggested_title_ar ?? $this->getKey());
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return ['status' => ['ar' => 'الحالة', 'en' => 'Status']];
    }
}
