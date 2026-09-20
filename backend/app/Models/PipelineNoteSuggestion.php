<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Stand-in for F7's proposal flow: a contributor's suggested status_research note awaiting editor acceptance. */
class PipelineNoteSuggestion extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var array<int, string> */
    public $guarded = [];
}
