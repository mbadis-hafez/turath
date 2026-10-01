<?php

namespace App\Support\Ocr\Dataset;

use App\Models\File;
use App\Models\OcrDatasetDocument;
use App\Models\OcrDatasetExport;
use App\Models\OcrEvaluationReservedFile;
use App\Models\OcrReviewExample;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use InvalidArgumentException;

/**
 * Writes one split's examples as JSON Lines — the representation a future
 * evaluation run or fine-tune would read. Nothing is trained here.
 *
 * - One split per file, and a document belongs to exactly one split.
 * - The latest decision on each thing counts; an undone match is left out.
 * - Only archive items at an exportable access level (privacy rule 10: an
 *   export leaves the app's access control, so nothing institution-only or
 *   embargoed goes into one).
 * - A training document whose file is also an evaluation document's, or a
 *   reserved benchmark file, is held back: the same scan must never be in both.
 * - No reviewer, item or file ids: documents are opaque references.
 * - Every document written is locked to its split from then on.
 */
class DatasetExporter
{
    public function __construct(private readonly DatasetSplits $splits) {}

    /**
     * @return array{path: string, examples: int, documents: int, held_back: list<int>, not_exportable: int}
     */
    public function export(string $split, ?string $path = null): array
    {
        if (! in_array($split, [OcrDatasetDocument::SPLIT_TRAINING, OcrDatasetDocument::SPLIT_EVALUATION], true)) {
            throw new InvalidArgumentException('Only training or evaluation data can be exported.');
        }

        $documents = OcrDatasetDocument::query()->with('archiveItem')->where('split', $split)->get();
        $exportable = (array) config('ocr.dataset.exportable_access_levels');
        $allowed = $documents->filter(fn (OcrDatasetDocument $d) => $d->archiveItem !== null && in_array($d->archiveItem->access_level, $exportable, true));
        $heldBack = $split === OcrDatasetDocument::SPLIT_TRAINING ? $this->sharedWithEvaluation($allowed) : [];
        $itemIds = $allowed->pluck('archive_item_id')->diff($heldBack)->values();

        $examples = OcrReviewExample::latestDecisions()->whereIn('archive_item_id', $itemIds)->orderBy('id')->get();

        $path ??= rtrim((string) config('ocr.dataset.export_path'), '/')."/{$split}-".now()->format('Ymd-His').'.jsonl';
        Filesystem::ensureDirectoryExists(dirname($path));
        $lines = $examples->map(fn (OcrReviewExample $e) => json_encode($this->line($e, $split), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->all();
        Filesystem::put($path, $lines === [] ? '' : implode("\n", $lines)."\n");

        $written = $examples->pluck('archive_item_id')->filter()->unique()->values();
        DB::transaction(function () use ($written, $split, $examples, $path) {
            OcrDatasetDocument::query()->whereIn('archive_item_id', $written)->whereNull('locked_at')->update(['locked_at' => now()]);
            OcrDatasetExport::create([
                'split' => $split,
                'example_count' => $examples->count(),
                'document_count' => $written->count(),
                'path' => $path,
                'sha256' => (string) hash_file('sha256', $path),
            ]);
        });

        return [
            'path' => $path,
            'examples' => $examples->count(),
            'documents' => $written->count(),
            'held_back' => array_map('intval', $heldBack),
            'not_exportable' => $documents->count() - $allowed->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function line(OcrReviewExample $example, string $split): array
    {
        $ai = $example->ai_meta;

        return [
            'id' => $example->id,
            'document' => $example->archive_item_id === null ? null : $this->splits->reference($example->archive_item_id),
            'split' => $split,
            'document_type' => $example->document_type,
            'language' => $example->language,
            'kind' => $example->kind,
            'key' => $example->key,
            'ocr_text' => $example->ocr_text,
            'ai_correction' => $example->ai_correction,
            'machine_suggestion' => $example->machine_suggestion,
            'human_correction' => $example->human_correction,
            'accepted' => $example->accepted,
            'reviewer_action' => $example->reviewer_action,
            'extraction_method' => $example->extraction_method,
            'confidence' => $example->confidence,
            'model' => $ai === null ? null : array_intersect_key($ai, array_flip(['kind', 'provider', 'model', 'model_version', 'prompt_version', 'rules_version', 'status', 'changed', 'needs_review'])),
            'match' => $example->match,
            'redacted' => $example->redacted,
            'decided_on' => $example->decided_at->toDateString(),
        ];
    }

    /**
     * Training documents that share a file with an evaluation document or a
     * reserved benchmark file.
     *
     * @param  Collection<int, OcrDatasetDocument>  $training
     * @return list<mixed>
     */
    private function sharedWithEvaluation(Collection $training): array
    {
        $evaluationItems = OcrDatasetDocument::query()->where('split', OcrDatasetDocument::SPLIT_EVALUATION)->pluck('archive_item_id');
        $guarded = File::query()->whereIn('archive_item_id', $evaluationItems)->pluck('sha256')
            ->merge(OcrEvaluationReservedFile::query()->pluck('sha256'))
            ->filter()->unique();

        return File::query()
            ->whereIn('archive_item_id', $training->pluck('archive_item_id'))
            ->whereIn('sha256', $guarded)
            ->pluck('archive_item_id')->unique()->values()->all();
    }
}
