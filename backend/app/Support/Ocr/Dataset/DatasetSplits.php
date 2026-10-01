<?php

namespace App\Support\Ocr\Dataset;

use App\Models\File;
use App\Models\OcrDatasetDocument;
use App\Models\OcrEvaluationReservedFile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Which split an archive item's examples belong to. A document is the unit:
 * every example from one item goes to the same split, so a model is never
 * evaluated on a document it was trained on.
 *
 * - Assigned once, when its first example is recorded: a reserved checksum
 *   (a benchmark document) makes it evaluation; a file identical to one
 *   already assigned (the same scan uploaded twice) takes that split; anything
 *   else is a keyed hash of the item id, so the choice is stable and spread.
 * - Locked when first exported. A locked document can still be excluded from
 *   later exports, but never moved between training and evaluation.
 */
class DatasetSplits
{
    public function assign(int $archiveItemId): OcrDatasetDocument
    {
        $existing = OcrDatasetDocument::query()->where('archive_item_id', $archiveItemId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $hashes = File::query()->where('archive_item_id', $archiveItemId)->pluck('sha256')->filter()->values();
        [$split, $by, $reason] = match (true) {
            $hashes->isNotEmpty() && OcrEvaluationReservedFile::query()->whereIn('sha256', $hashes)->exists() => [OcrDatasetDocument::SPLIT_EVALUATION, 'reserved', 'Benchmark document.'],
            default => $this->twin($archiveItemId, $hashes->all()) ?? [$this->bucket($archiveItemId), 'automatic', null],
        };

        try {
            return OcrDatasetDocument::create(['archive_item_id' => $archiveItemId, 'split' => $split, 'assigned_by' => $by, 'reason' => $reason]);
        } catch (UniqueConstraintViolationException) {
            // Two decisions on the same item at once: the first assignment stands.
            return OcrDatasetDocument::query()->where('archive_item_id', $archiveItemId)->firstOrFail();
        }
    }

    /** A person's choice, within what can no longer leak. */
    public function set(int $archiveItemId, string $split, ?User $user, ?string $reason): OcrDatasetDocument
    {
        if (! in_array($split, OcrDatasetDocument::SPLITS, true)) {
            throw ValidationException::withMessages(['split' => ['The split must be training, evaluation or excluded.']]);
        }

        $document = $this->assign($archiveItemId);
        if ($document->split === $split) {
            return $document;
        }
        if ($document->locked_at !== null && $split !== OcrDatasetDocument::SPLIT_EXCLUDED) {
            throw ValidationException::withMessages(['split' => ["Already exported as {$document->split} data; moving it to {$split} would let one set leak into the other. It can only be excluded."]]);
        }
        if ($split === OcrDatasetDocument::SPLIT_TRAINING && $document->assigned_by === 'reserved') {
            throw ValidationException::withMessages(['split' => ['A benchmark document is evaluation data only.']]);
        }

        $document->update(['split' => $split, 'assigned_by' => 'reviewer', 'assigned_by_user_id' => $user?->id, 'reason' => $reason]);

        return $document;
    }

    /**
     * Reserve a file checksum for evaluation, and move any archive item holding
     * that file there. An item already exported as training can't be moved —
     * it is returned as a conflict, for a person to look at.
     *
     * @return array{moved: list<int>, conflicts: list<int>}
     */
    public function reserveForEvaluation(string $sha256, string $source): array
    {
        OcrEvaluationReservedFile::query()->firstOrCreate(['sha256' => $sha256], ['source' => $source]);

        $moved = [];
        $conflicts = [];
        foreach (File::query()->where('sha256', $sha256)->pluck('archive_item_id')->unique() as $itemId) {
            $document = OcrDatasetDocument::query()->where('archive_item_id', $itemId)->first();
            if ($document !== null && $document->split === OcrDatasetDocument::SPLIT_TRAINING && $document->locked_at !== null) {
                $conflicts[] = (int) $itemId;

                continue;
            }
            if ($document === null) {
                $this->assign((int) $itemId);
            } elseif ($document->split !== OcrDatasetDocument::SPLIT_EVALUATION) {
                $document->update(['split' => OcrDatasetDocument::SPLIT_EVALUATION, 'assigned_by' => 'reserved', 'reason' => 'Benchmark document.']);
                $moved[] = (int) $itemId;
            }
        }

        return ['moved' => $moved, 'conflicts' => $conflicts];
    }

    /** The automatic split for an item: a keyed hash, so it is stable and not guessable from the id. */
    public function bucket(int $archiveItemId): string
    {
        $hash = hash_hmac('sha256', "split:{$archiveItemId}", (string) config('ocr.dataset.split_salt'));
        $percent = hexdec(substr($hash, 0, 8)) % 100;

        return $percent < (int) config('ocr.dataset.evaluation_percent') ? OcrDatasetDocument::SPLIT_EVALUATION : OcrDatasetDocument::SPLIT_TRAINING;
    }

    /** An opaque, stable reference to a document for exported data — groups examples without naming the item. */
    public function reference(int $archiveItemId): string
    {
        return substr(hash_hmac('sha256', "document:{$archiveItemId}", (string) config('ocr.dataset.split_salt')), 0, 16);
    }

    /**
     * @param  list<mixed>  $hashes
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function twin(int $archiveItemId, array $hashes): ?array
    {
        if ($hashes === []) {
            return null;
        }

        $twin = OcrDatasetDocument::query()
            ->whereIn('archive_item_id', File::query()->whereIn('sha256', $hashes)->where('archive_item_id', '!=', $archiveItemId)->select('archive_item_id'))
            ->orderBy('id')
            ->first();

        return $twin === null ? null : [$twin->split, 'automatic', "Same file as archive item #{$twin->archive_item_id}."];
    }
}
