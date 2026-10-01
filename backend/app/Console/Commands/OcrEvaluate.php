<?php

namespace App\Console\Commands;

use App\Models\OcrDatasetDocument;
use App\Models\OcrReviewExample;
use App\Support\Ocr\Evaluation\ExampleEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Per-stage metrics from reviewer decisions (ExampleEvaluator): OCR, AI
 * correction, extraction, entity matching and the human workflow. Aggregates
 * only — no document text is printed.
 */
class OcrEvaluate extends Command
{
    protected $signature = 'ocr:evaluate
        {--split=evaluation : evaluation, training or all}
        {--document-type= : Only examples from documents of this type}
        {--since= : Only decisions made on or after this date (YYYY-MM-DD)}
        {--json : Print the metrics as JSON}';

    protected $description = 'Measure each OCR stage against what reviewers decided';

    public function handle(ExampleEvaluator $evaluator): int
    {
        $split = (string) $this->option('split');
        if (! in_array($split, [OcrDatasetDocument::SPLIT_EVALUATION, OcrDatasetDocument::SPLIT_TRAINING, 'all'], true)) {
            $this->error('--split must be evaluation, training or all.');

            return self::FAILURE;
        }

        $query = OcrReviewExample::latestDecisions();
        if ($split !== 'all') {
            $query->whereIn('archive_item_id', OcrDatasetDocument::query()->where('split', $split)->select('archive_item_id'));
        }
        if ($type = $this->option('document-type')) {
            $query->where('document_type', $type);
        }
        if ($since = $this->option('since')) {
            $query->where('decided_at', '>=', Carbon::parse((string) $since)->startOfDay());
        }

        $metrics = $evaluator->evaluate($query->get());

        if ($this->option('json')) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info("Split: {$split}. Examples: ".json_encode($metrics['examples']));
        foreach (['ocr', 'correction', 'extraction', 'matching', 'workflow'] as $stage) {
            $rows = [];
            foreach ($metrics[$stage] as $name => $value) {
                if (! is_array($value)) {
                    $rows[] = [$name, is_float($value) ? number_format($value * 100, 1).'%' : ($value ?? '—')];
                }
            }
            $this->table([ucfirst($stage), ''], $rows);
        }

        return self::SUCCESS;
    }
}
