<?php

namespace App\Console\Commands;

use App\Support\Ocr\Dataset\DatasetSplits;
use Illuminate\Console\Command;

/**
 * Reserves files for evaluation by checksum — the benchmark documents, kept
 * locally — so if the same scan is (or gets) uploaded to the archive, its
 * examples are evaluation data and never training data. Reads only the files'
 * checksums; safe to run anywhere.
 */
class OcrDatasetReserve extends Command
{
    protected $signature = 'ocr:dataset:reserve {files* : Local files to reserve}';

    protected $description = 'Keep these files (by checksum) out of OCR training data';

    public function handle(DatasetSplits $splits): int
    {
        $status = self::SUCCESS;
        foreach ($this->argument('files') as $path) {
            if (! is_file($path)) {
                $this->error("Not a file: {$path}");
                $status = self::FAILURE;

                continue;
            }
            $result = $splits->reserveForEvaluation((string) hash_file('sha256', $path), 'benchmark');
            $this->info(basename($path).': reserved'.($result['moved'] !== [] ? '; moved to evaluation: archive items '.implode(', ', $result['moved']) : '').'.');
            if ($result['conflicts'] !== []) {
                $this->warn('Already exported as training data, so not moved — review these archive items: '.implode(', ', $result['conflicts']).'.');
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
