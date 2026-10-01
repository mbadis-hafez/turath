<?php

namespace App\Models\Observers;

use App\Support\Ocr\Dataset\ReviewExampleRecorder;
use Illuminate\Database\Eloquent\Model;

/** Hands every OCR review decision to the example recorder, on the models where decisions are made. */
class RecordsOcrReviewDecisions
{
    public function updated(Model $model): void
    {
        app(ReviewExampleRecorder::class)->observe($model);
    }
}
