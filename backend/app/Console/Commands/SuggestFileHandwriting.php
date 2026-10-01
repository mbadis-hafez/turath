<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Support\Ocr\HandwritingOcrException;
use App\Support\Ocr\HandwritingSuggestionService;
use Illuminate\Console\Command;

/**
 * Requests handwriting suggestions for every eligible crop of one file — for
 * evaluating a provider against real material. Suggestions are still only
 * suggestions: nothing here settles a field.
 */
class SuggestFileHandwriting extends Command
{
    protected $signature = 'ocr:suggest-handwriting {file : The file id} {--dry-run : Report what would be sent without calling the provider}';

    protected $description = 'Request machine suggestions for a file\'s handwriting crops (never applied without a reviewer)';

    public function handle(HandwritingSuggestionService $service): int
    {
        $file = File::find((int) $this->argument('file'));
        if ($file === null) {
            $this->error('File not found.');

            return self::FAILURE;
        }

        try {
            $summary = $service->suggestForFile($file, null, (bool) $this->option('dry-run'));
        } catch (HandwritingOcrException $e) {
            $this->error($e->getMessage());
            $this->line('Suggestions stored before the failure are kept and will not be requested again.');

            return self::FAILURE;
        }

        if ($summary['refused'] !== null) {
            $this->warn("Nothing sent: {$summary['refused']}.");

            return self::SUCCESS;
        }

        $rows = [['eligible crops', $summary['eligible']], ['already suggested', $summary['cached']]];
        $rows[] = $this->option('dry-run') ? ['would call provider', $summary['would_call']] : ['provider calls', $summary['provider_calls']];
        foreach ($summary['skipped'] as $reason => $count) {
            $rows[] = ["skipped: {$reason}", $count];
        }
        $this->table(['', 'count'], $rows);

        return self::SUCCESS;
    }
}
