<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An audit row for every dataset file written (Dataset\DatasetExporter). */
class OcrDatasetExport extends Model
{
    /** @var array<int, string> */
    public $guarded = [];
}
