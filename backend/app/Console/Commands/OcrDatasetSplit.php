<?php

namespace App\Console\Commands;

use App\Support\Ocr\Dataset\DatasetSplits;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Shows or sets which dataset split an archive item's examples belong to. */
class OcrDatasetSplit extends Command
{
    protected $signature = 'ocr:dataset:split
        {archive_item : Archive item id}
        {split? : training, evaluation or excluded (omit to show the current one)}
        {--reason= : Why, for the record}';

    protected $description = 'Show or set an archive item\'s OCR dataset split';

    public function handle(DatasetSplits $splits): int
    {
        $itemId = (int) $this->argument('archive_item');
        $split = $this->argument('split');

        try {
            $document = $split === null
                ? $splits->assign($itemId)
                : $splits->set($itemId, (string) $split, null, $this->option('reason') ? (string) $this->option('reason') : null);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info("Archive item #{$itemId}: {$document->split} ({$document->assigned_by})".($document->locked_at !== null ? ', locked since '.$document->locked_at->toDateString() : '').'.');

        return self::SUCCESS;
    }
}
