<?php

namespace App\Support\Completeness;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\RecordCompleteness;
use App\Models\ReviewQueueItem;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Shared "my records" query logic behind both the dashboard record list and
 * the gaps-report export (D54) — the export must be scoped to exactly the
 * same filters/access as what's on screen.
 */
class DashboardRecordsCollector
{
    /**
     * @var array<string, class-string>
     */
    public const ENTITY_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'archive_item' => ArchiveItem::class,
    ];

    /**
     * @param  array<int, string>  $entityTypes
     * @param  array<int, string>  $severities
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(int $userId, array $entityTypes, array $severities): Collection
    {
        return $this->build($entityTypes, $severities, fn (string $modelClass) => $modelClass::query()
            ->where('created_by_user_id', $userId)->get());
    }

    /**
     * The gaps a user should see on their own dashboard: their own records,
     * plus — if they hold any review_queue.* permission — records pending
     * review in the queues they work. A user can be both a record creator
     * and a reviewer (e.g. dashboard.manage no longer implies "not also a
     * reviewer"), so these are additive rather than either/or.
     *
     * @param  array<int, string>  $reviewTypes
     * @param  array<int, string>  $entityTypes
     * @param  array<int, string>  $severities
     * @return Collection<int, array<string, mixed>>
     */
    public function collectForUser(int $userId, array $reviewTypes, array $entityTypes, array $severities): Collection
    {
        $own = $this->collect($userId, $entityTypes, $severities);

        if ($reviewTypes === []) {
            return $own;
        }

        return $this->sorted(
            $own->concat($this->collectForReview($reviewTypes, $entityTypes, $severities))
                ->unique(fn (array $row) => $row['entity_type'].':'.$row['id']),
        );
    }

    /**
     * A reviewer's gaps view: records with a pending review-queue item in one
     * of the review types the reviewer holds review_queue.* for — the same
     * scoping /proposals applies (ProposalController::reviewableTypes),
     * rather than the "records I created" scoping `collect()` uses.
     *
     * @param  array<int, string>  $reviewTypes
     * @param  array<int, string>  $entityTypes
     * @param  array<int, string>  $severities
     * @return Collection<int, array<string, mixed>>
     */
    public function collectForReview(array $reviewTypes, array $entityTypes, array $severities): Collection
    {
        if ($reviewTypes === []) {
            return collect();
        }

        return $this->build($entityTypes, $severities, function (string $modelClass) use ($reviewTypes) {
            $citableIds = ReviewQueueItem::query()
                ->where('citable_type', $modelClass)
                ->where('status', 'pending')
                ->whereIn('review_type', $reviewTypes)
                ->pluck('citable_id');

            return $citableIds->isEmpty()
                ? new EloquentCollection
                : $modelClass::query()->whereIn('id', $citableIds)->get();
        });
    }

    /**
     * @param  array<int, string>  $entityTypes
     * @param  array<int, string>  $severities
     * @param  callable(class-string): EloquentCollection<int, Artist|Artwork|ArchiveItem>  $recordsFor
     * @return Collection<int, array<string, mixed>>
     */
    private function build(array $entityTypes, array $severities, callable $recordsFor): Collection
    {
        $rows = collect();

        foreach (self::ENTITY_TYPES as $key => $modelClass) {
            if (! in_array($key, $entityTypes, true)) {
                continue;
            }

            $records = $recordsFor($modelClass);
            $completenessById = RecordCompleteness::query()
                ->where('citable_type', $modelClass)
                ->whereIn('citable_id', $records->pluck('id'))
                ->get()
                ->keyBy('citable_id');

            foreach ($records as $record) {
                $completeness = $completenessById->get($record->id);
                $severity = $completeness->severity ?? 'blocking';

                if ($severities !== [] && ! in_array($severity, $severities, true)) {
                    continue;
                }

                $rows->push([
                    'entity_type' => $key,
                    'id' => $record->id,
                    'slug' => $key === 'artist' ? $record->getAttribute('slug') : null,
                    'title' => $this->titleFor($key, $record),
                    'completeness_pct' => $completeness->completeness_pct ?? 0,
                    'severity' => $severity,
                    'blocking_gaps' => $completeness->blocking_gap_field_keys ?? [],
                    'minor_gaps' => $completeness->minor_gap_field_keys ?? [],
                    'open_conflict_count' => $completeness->open_conflict_count ?? 0,
                ]);
            }
        }

        return $this->sorted($rows);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sorted(Collection $rows): Collection
    {
        return $rows->sortBy(fn (array $row) => match ($row['severity']) {
            'blocking' => 0,
            'conflict' => 1,
            'minor' => 2,
            'pending_review' => 3,
            default => 4,
        })->values();
    }

    /**
     * @return array{ar: string|null, en: string|null}
     */
    private function titleFor(string $entityType, Artist|Artwork|ArchiveItem $record): array
    {
        return match ($entityType) {
            'artist' => ['ar' => $record->name_ar, 'en' => $record->name_en],
            'artwork' => ['ar' => $record->title_ar, 'en' => $record->title_en],
            default => ['ar' => $record->title_ar, 'en' => $record->title_en],
        };
    }
}
