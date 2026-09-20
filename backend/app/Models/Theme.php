<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use LogsChanges;

    /** @var array<int, string> */
    public $guarded = [];

    public function activitySubjectLabel(): string
    {
        return 'Theme: '.($this->label_en ?? $this->label_ar ?? $this->getKey());
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return ['label_ar' => ['ar' => 'التسمية (عربي)', 'en' => 'Label (Arabic)'], 'label_en' => ['ar' => 'التسمية (إنجليزي)', 'en' => 'Label (English)']];
    }
}
