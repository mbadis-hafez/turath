<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A file checksum that may only ever be evaluation data (a benchmark document), wherever it is uploaded. */
class OcrEvaluationReservedFile extends Model
{
    /** @var array<int, string> */
    public $guarded = [];
}
