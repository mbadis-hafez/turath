<?php

namespace Tests\Fixtures;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackableThing extends Model
{
    use LogsChanges, SoftDeletes;

    protected $table = 'trackable_things';

    /** @var array<int, string> */
    public $guarded = [];

    public function activitySubjectLabel(): string
    {
        return 'Thing: '.$this->title;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'title' => ['ar' => 'العنوان', 'en' => 'Title'],
            'birth_year_from' => ['ar' => 'سنة الميلاد (من)', 'en' => 'Birth year (from)'],
        ];
    }
}
