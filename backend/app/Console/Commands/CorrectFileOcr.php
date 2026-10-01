<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Support\Ocr\Correction\OcrCorrectionException;
use App\Support\Ocr\Correction\OcrCorrectionService;
use Illuminate\Console\Command;

/**
 * Runs AI correction over one file's printed-text OCR regions. The manual
 * entry point until the queued pipeline exists; --dry-run reports what would
 * be sent (and what is already cached) without calling any provider.
 */
class CorrectFileOcr extends Command
{
    protected $signature = 'ocr:correct {file : The file id} {--dry-run : Report what would be sent without calling the provider or writing anything}';

    protected $description = 'AI-correct the printed-text OCR regions of one file (stored beside the OCR text, never replacing it)';

    public function handle(OcrCorrectionService $service): int
    {
        $file = File::find((int) $this->argument('file'));
        if ($file === null) {
            $this->error('File not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $summary = $service->correctFile($file, $dryRun);
        } catch (OcrCorrectionException $e) {
            $this->error($e->getMessage());
            $this->line($e->retryable ? 'Retryable: regions already corrected are kept and will not be re-sent.' : 'Not retryable: check the provider configuration.');

            return self::FAILURE;
        }

        if ($summary['refused'] !== null) {
            $this->warn("Nothing sent: {$summary['refused']}.");

            return self::SUCCESS;
        }

        $rows = [['eligible regions', $summary['eligible']], ['cache hits', $summary['cache_hits']]];
        if ($dryRun) {
            $rows[] = ['would call provider', $summary['would_call']];
            $rows[] = ['characters that would be sent', $summary['would_send_chars']];
        } else {
            $rows[] = ['provider calls', $summary['provider_calls']];
            foreach ($summary['statuses'] as $status => $count) {
                $rows[] = ["result: {$status}", $count];
            }
        }
        foreach ($summary['skipped'] as $reason => $count) {
            $rows[] = ["skipped: {$reason}", $count];
        }

        $this->table(['', 'count'], $rows);

        return self::SUCCESS;
    }
}
