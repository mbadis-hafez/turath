<?php

namespace App\Console\Commands;

use App\Support\Ocr\Benchmark\BenchmarkManifest;
use App\Support\Ocr\Benchmark\BenchmarkReport;
use App\Support\Ocr\Benchmark\BenchmarkRunner;
use App\Support\Ocr\Benchmark\BenchmarkScorer;
use App\Support\Ocr\Dataset\DatasetSplits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File as Filesystem;
use InvalidArgumentException;

/**
 * Runs the local benchmark suite (BenchmarkManifest) through the real
 * pipeline and scores it against the known answers. Nothing is kept in the
 * database. The report holds counts and rates, never document text, so it is
 * safe to print even for documents with personal data.
 *
 * Each benchmark file's checksum is reserved for evaluation, so the same scan
 * uploaded to the archive can never become training data.
 */
class OcrBenchmark extends Command
{
    protected $signature = 'ocr:benchmark
        {--manifest= : The suite manifest (default: config ocr.benchmark.manifest)}
        {--only=* : Run only these document ids}
        {--with-correction : Also run AI correction (costs money; follows the provider and privacy settings)}
        {--report= : Where to write the JSON report (default: under config ocr.benchmark.report_path)}';

    protected $description = 'Score the OCR pipeline against the local benchmark suite';

    public function handle(BenchmarkRunner $runner, BenchmarkScorer $scorer, DatasetSplits $splits): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('The benchmark never runs in production. To keep its files out of training data there, run ocr:dataset:reserve.');

            return self::FAILURE;
        }

        try {
            $manifest = BenchmarkManifest::load((string) ($this->option('manifest') ?: config('ocr.benchmark.manifest')));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $only = (array) $this->option('only');
        $documents = array_values(array_filter($manifest->documents, fn ($d) => $only === [] || in_array($d->id, $only, true)));
        if ($documents === []) {
            $this->error('No benchmark documents to run.');

            return self::FAILURE;
        }

        $scores = [];
        foreach ($documents as $document) {
            $splits->reserveForEvaluation((string) hash_file('sha256', $document->file), 'benchmark');
            $this->line("Running {$document->id} ({$document->category})…");
            $scores[] = $scorer->score($document, $runner->run($document, (bool) $this->option('with-correction')));
        }
        $report = BenchmarkReport::summarize($scores, $manifest);

        $this->table(
            ['Document', 'Category', 'Type', 'Field P', 'Field R', 'Date P', 'Date R', 'CER', 'WER'],
            array_map(fn (array $s) => [
                $s['id'], $s['category'],
                $s['document_type'] === null ? '—' : ($s['document_type']['correct'] ? 'yes' : 'no'),
                $this->pct($s['fields']['precision'] ?? null), $this->pct($s['fields']['recall'] ?? null),
                $this->pct($s['dates']['precision'] ?? null), $this->pct($s['dates']['recall'] ?? null),
                $this->pct($s['pages']['cer'] ?? null), $this->pct($s['pages']['wer'] ?? null),
            ], $scores),
        );
        $this->table(['Overall', 'Value'], array_map(fn ($k, $v) => [$k, $this->pct($v)], array_keys($report['overall']), $report['overall']));

        if ($report['coverage']['missing'] !== []) {
            $this->warn("The suite covers {$report['coverage']['covered']} of {$report['coverage']['categories']} document categories. Missing: ".implode(', ', $report['coverage']['missing']).'.');
        }

        $path = (string) ($this->option('report') ?: rtrim((string) config('ocr.benchmark.report_path'), '/').'/benchmark-'.now()->format('Ymd-His').'.json');
        Filesystem::ensureDirectoryExists(dirname($path));
        Filesystem::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info("Report written to {$path}");

        return self::SUCCESS;
    }

    private function pct(mixed $value): string
    {
        return is_float($value) || is_int($value) ? number_format($value * 100, 1).'%' : '—';
    }
}
