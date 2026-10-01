<?php

namespace App\Console\Commands;

use App\Support\Ocr\Dataset\DatasetExporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Writes one split's examples as JSON Lines (DatasetExporter). No model is trained from it here. */
class OcrDatasetExport extends Command
{
    protected $signature = 'ocr:dataset:export
        {split : training or evaluation}
        {--output= : The file to write (default: under config ocr.dataset.export_path)}';

    protected $description = 'Export reviewer decisions as an OCR training or evaluation dataset';

    public function handle(DatasetExporter $exporter): int
    {
        try {
            $result = $exporter->export((string) $this->argument('split'), $this->option('output') ? (string) $this->option('output') : null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Wrote {$result['examples']} examples from {$result['documents']} documents to {$result['path']}.");
        if ($result['not_exportable'] > 0) {
            $this->line("{$result['not_exportable']} documents left out: their access level isn't exportable (privacy rule 10).");
        }
        if ($result['held_back'] !== []) {
            $this->warn(count($result['held_back']).' training documents held back: the same file is evaluation data. Archive items: '.implode(', ', $result['held_back']).'.');
        }

        return self::SUCCESS;
    }
}
