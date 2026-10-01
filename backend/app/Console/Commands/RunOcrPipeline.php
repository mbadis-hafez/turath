<?php

namespace App\Console\Commands;

use App\Enums\OcrStage;
use App\Models\File;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Brings files' OCR up to date: resumes pipelines that stopped (a failed or
 * lost stage), and after a stage's VERSION is bumped, reprocesses only that
 * stage and whatever depends on it. Stages whose input is unchanged are
 * skipped, so running this twice does nothing the second time.
 */
class RunOcrPipeline extends Command
{
    protected $signature = 'ocr:pipeline
        {files?* : File ids}
        {--all : Every image and PDF file}
        {--from=recognize : The stage to start from (recognize, extract, correct); earlier stages are left alone}
        {--force : Run the --from stage even if its input is unchanged}
        {--dry-run : Show what would run, without queueing anything}';

    protected $description = 'Queue the OCR pipeline for files, skipping stages that are already up to date';

    public function handle(OcrPipeline $pipeline): int
    {
        $from = OcrStage::tryFrom((string) $this->option('from'));
        if ($from === null) {
            $this->error('--from must be one of: '.implode(', ', array_column(OcrStage::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $ids = array_map('intval', (array) $this->argument('files'));
        if ($ids === [] && ! $this->option('all')) {
            $this->error('Give file ids, or --all.');

            return self::FAILURE;
        }

        $query = File::query()
            ->when($ids !== [], fn (Builder $q) => $q->whereIn('id', $ids))
            ->where(fn (Builder $q) => $q->where('mime_type', 'application/pdf')->orWhere('mime_type', 'like', 'image/%'))
            ->orderBy('id');

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $rows = [];
        $query->chunkById(200, function ($files) use ($pipeline, $from, $force, $dryRun, &$rows) {
            foreach ($files as $file) {
                $rows[] = $dryRun
                    ? [$file->id, ...array_values($pipeline->plan($file, $from, $force))]
                    : [$file->id, $pipeline->start($file, $from, $force)];
            }
        });

        if ($rows === []) {
            $this->warn('No image or PDF files matched.');

            return self::SUCCESS;
        }

        $this->table($dryRun ? ['file', ...array_column($from->andAfter(), 'value')] : ['file', 'result'], $rows);

        return self::SUCCESS;
    }
}
