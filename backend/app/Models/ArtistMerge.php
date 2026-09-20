<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ArtistMerge extends Model
{
    use HasUuids, LogsChanges;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['field_resolution' => 'array'];
    }

    public function activitySubjectLabel(): string
    {
        return "Merge of artist #{$this->merged_artist_id} into #{$this->survivor_artist_id}";
    }
}
