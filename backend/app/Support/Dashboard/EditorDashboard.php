<?php

namespace App\Support\Dashboard;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the editor dashboard for exactly one user. Everything is
 * hard-scoped to the given user id — the controller never accepts a user
 * id from the request, so nothing here can leak another user's records or
 * org-wide stats.
 *
 * All counts are query-builder aggregates (COUNT / SUM with CASE-style
 * conditions) and every row list is bounded (≤ 8 rows before hydration).
 * record_completeness is read as the materialized source of truth — it is
 * never recalculated here.
 */
class EditorDashboard
{
    /** Entity types that have record_completeness rows. */
    private const CITABLE_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'archive_item' => ArchiveItem::class,
    ];

    /** @var array<string, class-string> */
    private const ENTITY_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'event' => Event::class,
        'archive_item' => ArchiveItem::class,
    ];

    /** content_overview keys (plural, unlike the singular entity keys). */
    private const OVERVIEW_KEYS = [
        'artist' => 'artists',
        'artwork' => 'artworks',
        'event' => 'events',
        'archive_item' => 'archive_items',
    ];

    /** @var array<string, string> */
    private const TABLES = [
        'artist' => 'artists',
        'artwork' => 'artworks',
        'event' => 'events',
        'archive_item' => 'archive_items',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forUser(int $userId): array
    {
        return [
            'my_work' => $this->myWork($userId),
            'needs_attention' => $this->needsAttention($userId),
            'content_overview' => $this->contentOverview($userId),
            'review_pipeline' => $this->reviewPipeline($userId),
            'completeness' => $this->completeness($userId),
            'archive' => $this->archive($userId),
            'continue_working' => $this->continueWorking($userId),
            'recent_activity' => $this->recentActivity($userId),
        ];
    }

    /**
     * @return array{drafts: int, in_progress: int, ready_for_review: int, changes_requested: int}
     */
    private function myWork(int $userId): array
    {
        $statuses = $this->proposalCounts($userId);

        return [
            'drafts' => $this->countDraftRecords($userId),
            'in_progress' => (int) ($statuses['draft'] ?? 0),
            'ready_for_review' => (int) ($statuses['pending'] ?? 0),
            'changes_requested' => (int) ($statuses['changes_requested'] ?? 0),
        ];
    }

    /**
     * Priority list, max 8: (1) my proposals with status=changes_requested,
     * newest first; (2) my records whose materialized completeness severity
     * is blocking or minor, lowest pct first; (3) my records whose creation
     * review has not been approved yet. Records already listed under (1) are
     * excluded from (2) and (3) (and (2) from (3)) so nothing is duplicated.
     *
     * @return array<int, array<string, mixed>>
     */
    private function needsAttention(int $userId): array
    {
        $items = [];
        $skip = [];

        $proposals = DB::table('edit_proposals')
            ->where('proposed_by_user_id', $userId)
            ->where('status', 'changes_requested')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $recordsByKey = [];
        $completenessByKey = [];
        foreach ($proposals->groupBy('citable_type') as $citableType => $group) {
            $entityType = $this->entityKeyFor($citableType);
            if ($entityType === null) {
                continue;
            }
            $ids = $group->pluck('citable_id')->map(fn ($id) => (int) $id)->all();
            foreach ($this->recordsFor($entityType, $ids) as $id => $record) {
                $recordsByKey["{$entityType}:{$id}"] = [$entityType, $record];
            }
            foreach ($this->completenessFor($entityType, $ids) as $id => $completeness) {
                $completenessByKey["{$entityType}:{$id}"] = $completeness;
            }
        }

        foreach ($proposals as $proposal) {
            $entityType = $this->entityKeyFor($proposal->citable_type);
            $key = $entityType !== null ? "{$entityType}:".((int) $proposal->citable_id) : null;
            if ($key === null || isset($skip[$key]) || ! isset($recordsByKey[$key])) {
                continue;
            }
            [$type, $record] = $recordsByKey[$key];
            $completeness = $completenessByKey[$key] ?? null;
            $skip[$key] = true;
            $items[] = [
                'entity_type' => $type,
                'id' => $record['id'],
                'slug' => $record['slug'],
                'title' => $record['title'],
                'kind' => 'changes_requested',
                'completeness_pct' => $completeness !== null ? (int) $completeness->completeness_pct : null,
                'severity' => $completeness->severity ?? null,
                'reason_note' => $proposal->review_note,
                'updated_at' => $record['updated_at'],
            ];
        }

        $remaining = 8 - count($items);
        if ($remaining > 0) {
            foreach ($this->incompleteRecords($userId, $skip, $remaining) as $item) {
                $skip["{$item['entity_type']}:{$item['id']}"] = true;
                $items[] = $item;
            }
        }

        $remaining = 8 - count($items);
        if ($remaining > 0) {
            foreach ($this->creationPendingRecords($userId, $skip, $remaining) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Phase 2 of needs_attention: my citable records with completeness
     * severity blocking or minor, lowest pct first.
     *
     * @param  array<string, bool>  $skip
     * @return array<int, array<string, mixed>>
     */
    private function incompleteRecords(int $userId, array $skip, int $limit): array
    {
        $candidates = collect();

        foreach (self::CITABLE_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $skipIds = $this->skipIdsFor($entityType, $skip);
            $query = DB::table($table)
                ->join('record_completeness as rc', function ($join) use ($modelClass, $table) {
                    $join->on('rc.citable_id', '=', "{$table}.id")
                        ->where('rc.citable_type', '=', $modelClass);
                });
            $this->scopeMine($query, $entityType, $userId);
            $rows = $query->whereIn('rc.severity', ['blocking', 'minor'])
                ->when($skipIds !== [], fn (Builder $q) => $q->whereNotIn("{$table}.id", $skipIds))
                ->select(array_merge($this->titleColumns($entityType), ['rc.completeness_pct', 'rc.severity']))
                ->orderBy('rc.completeness_pct')
                ->orderBy("{$table}.id")
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $candidates->push($this->attentionItem($entityType, $row, 'incomplete', null));
            }
        }

        return $candidates->sortBy(fn (array $item) => $item['completeness_pct'] ?? 0)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Phase 3 of needs_attention: my records (any of the four types) still
     * waiting for their creation review to be approved.
     *
     * @param  array<string, bool>  $skip
     * @return array<int, array<string, mixed>>
     */
    private function creationPendingRecords(int $userId, array $skip, int $limit): array
    {
        $candidates = collect();

        foreach (self::ENTITY_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $skipIds = $this->skipIdsFor($entityType, $skip);
            $query = DB::table($table);
            $this->joinCompleteness($query, $entityType, $table);
            $this->scopeMine($query, $entityType, $userId);
            $select = array_merge($this->titleColumns($entityType), ['rc.completeness_pct', 'rc.severity']);
            $rows = $query->whereNull("{$table}.creation_approved_at")
                ->when($skipIds !== [], fn (Builder $q) => $q->whereNotIn("{$table}.id", $skipIds))
                ->select($select)
                ->orderByDesc("{$table}.updated_at")
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $candidates->push($this->attentionItem($entityType, $row, 'creation_pending', null));
            }
        }

        return $candidates->sortByDesc(fn (array $item) => $item['updated_at'])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function attentionItem(string $entityType, object $row, string $kind, ?string $reasonNote): array
    {
        return [
            'entity_type' => $entityType,
            'id' => (int) $row->id,
            'slug' => $row->slug ?? null,
            'title' => $this->titleFromRow($entityType, $row),
            'kind' => $kind,
            'completeness_pct' => isset($row->completeness_pct) ? (int) $row->completeness_pct : null,
            'severity' => $row->severity ?? null,
            'reason_note' => $reasonNote,
            'updated_at' => Carbon::parse($row->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array{total: int, incomplete: int}>
     */
    private function contentOverview(int $userId): array
    {
        $overview = [];

        foreach (self::ENTITY_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $query = DB::table($table);
            $this->joinCompleteness($query, $entityType, $table);
            $this->scopeMine($query, $entityType, $userId);
            $row = $query->selectRaw('COUNT(*) AS total')
                ->selectRaw('SUM(COALESCE(rc.completeness_pct, 100) < 100) AS incomplete')
                ->first();

            $overview[self::OVERVIEW_KEYS[$entityType]] = [
                'total' => (int) $row->total,
                'incomplete' => (int) $row->incomplete,
            ];
        }

        return $overview;
    }

    /**
     * Canonical definitions (see the controller docblock):
     * - draft: my records with publication_status=draft and no open proposal
     *   of mine (draft/pending/changes_requested)
     * - in_progress: my proposals with status=draft
     * - ready_for_review: my proposals with status=pending
     * - under_review: my records having a pending review_queue_item
     * - changes_requested: my proposals with status=changes_requested
     * - approved: my proposals with status=approved (informational)
     * - published: my records with publication_status=published
     *
     * @return array{draft: int, in_progress: int, ready_for_review: int, under_review: int, changes_requested: int, approved: int, published: int}
     */
    private function reviewPipeline(int $userId): array
    {
        $statuses = $this->proposalCounts($userId);

        return [
            'draft' => $this->countDraftRecords($userId),
            'in_progress' => (int) ($statuses['draft'] ?? 0),
            'ready_for_review' => (int) ($statuses['pending'] ?? 0),
            'under_review' => $this->countUnderReview($userId),
            'changes_requested' => (int) ($statuses['changes_requested'] ?? 0),
            'approved' => (int) ($statuses['approved'] ?? 0),
            'published' => $this->countPublishedRecords($userId),
        ];
    }

    /**
     * @return array{buckets: array{complete: int, high: int, medium: int, low: int}, lowest: array<int, array<string, mixed>>}
     */
    private function completeness(int $userId): array
    {
        $buckets = ['complete' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $lowest = collect();

        foreach (self::CITABLE_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $query = DB::table($table)
                ->join('record_completeness as rc', function ($join) use ($modelClass, $table) {
                    $join->on('rc.citable_id', '=', "{$table}.id")
                        ->where('rc.citable_type', '=', $modelClass);
                });
            $this->scopeMine($query, $entityType, $userId);
            $row = $query->selectRaw('SUM(rc.completeness_pct = 100) AS complete')
                ->selectRaw('SUM(rc.completeness_pct BETWEEN 80 AND 99) AS high')
                ->selectRaw('SUM(rc.completeness_pct BETWEEN 50 AND 79) AS medium')
                ->selectRaw('SUM(rc.completeness_pct < 50) AS low')
                ->first();

            foreach ($buckets as $bucket => $count) {
                $buckets[$bucket] = $count + (int) $row->{$bucket};
            }

            $lowQuery = DB::table($table)
                ->join('record_completeness as rc', function ($join) use ($modelClass, $table) {
                    $join->on('rc.citable_id', '=', "{$table}.id")
                        ->where('rc.citable_type', '=', $modelClass);
                });
            $this->scopeMine($lowQuery, $entityType, $userId);
            $rows = $lowQuery->select(array_merge($this->titleColumns($entityType), ['rc.completeness_pct']))
                ->orderBy('rc.completeness_pct')
                ->orderBy("{$table}.id")
                ->limit(3)
                ->get();

            foreach ($rows as $low) {
                $lowest->push([
                    'entity_type' => $entityType,
                    'id' => (int) $low->id,
                    'slug' => $low->slug ?? null,
                    'title' => $this->titleFromRow($entityType, $low),
                    'completeness_pct' => (int) $low->completeness_pct,
                ]);
            }
        }

        return [
            'buckets' => $buckets,
            'lowest' => $lowest->sortBy(fn (array $item) => $item['completeness_pct'])
                ->take(3)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{draft: int, incomplete: int, under_review: int, published: int, items: array<int, array<string, mixed>>}
     */
    private function archive(int $userId): array
    {
        $table = 'archive_items';
        $modelClass = ArchiveItem::class;

        $base = function () use ($table, $userId): Builder {
            $query = DB::table($table);
            $this->joinCompleteness($query, 'archive_item', $table);
            $query->where("{$table}.created_by_user_id", $userId);

            return $query;
        };

        $counts = $base()
            ->selectRaw("SUM(publication_status = 'published') AS published")
            ->selectRaw('SUM(publication_status = \'draft\' AND COALESCE(rc.completeness_pct, 100) < 100) AS incomplete')
            ->selectRaw("SUM(publication_status = 'draft' AND COALESCE(rc.completeness_pct, 100) = 100
                AND NOT EXISTS (
                    SELECT 1 FROM review_queue_items rqi
                    WHERE rqi.citable_type = ? AND rqi.citable_id = {$table}.id AND rqi.status = 'pending'
                )) AS draft", [$modelClass])
            ->first();

        $underReview = (int) $base()
            ->whereExists(function (Builder $sub) use ($table, $modelClass) {
                $sub->select(DB::raw(1))->from('review_queue_items')
                    ->whereColumn('review_queue_items.citable_id', "{$table}.id")
                    ->where('review_queue_items.citable_type', $modelClass)
                    ->where('review_queue_items.status', 'pending');
            })
            ->count();

        $rows = $base()
            ->addSelect("{$table}.id", "{$table}.title_ar", "{$table}.title_en", "{$table}.publication_status", 'rc.completeness_pct')
            ->orderByRaw('COALESCE(rc.completeness_pct, 100) ASC')
            ->orderBy("{$table}.id")
            ->limit(12)
            ->get();

        $pendingIds = $rows->pluck('id')->isEmpty() ? [] : DB::table('review_queue_items')
            ->where('citable_type', $modelClass)
            ->where('status', 'pending')
            ->whereIn('citable_id', $rows->pluck('id')->all())
            ->pluck('citable_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $items = [];
        foreach ($rows as $row) {
            $pct = $row->completeness_pct !== null ? (int) $row->completeness_pct : null;
            $isPending = $pendingIds->has((int) $row->id);

            if ($row->publication_status === 'draft' && ($pct ?? 100) < 100) {
                $status = 'incomplete';
            } elseif ($row->publication_status === 'draft') {
                $status = $isPending ? 'under_review' : 'draft';
            } elseif ($isPending) {
                $status = 'under_review';
            } else {
                continue;
            }

            $items[] = [
                'id' => (int) $row->id,
                'slug' => null,
                'title' => ['ar' => $row->title_ar, 'en' => $row->title_en],
                'status' => $status,
                'completeness_pct' => $pct,
            ];
        }

        $rank = ['incomplete' => 0, 'draft' => 1, 'under_review' => 2];
        usort($items, fn (array $a, array $b) => ($rank[$a['status']] <=> $rank[$b['status']])
            ?: (($a['completeness_pct'] ?? 100) <=> ($b['completeness_pct'] ?? 100)));

        return [
            'draft' => (int) $counts->draft,
            'incomplete' => (int) $counts->incomplete,
            'under_review' => $underReview,
            'published' => (int) $counts->published,
            'items' => array_slice($items, 0, 3),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function continueWorking(int $userId): array
    {
        $rows = DB::table('activity_log')
            ->where('causer_type', User::class)
            ->where('causer_id', (string) $userId)
            ->where('event', 'updated')
            ->whereIn('subject_type', array_values(self::ENTITY_TYPES))
            ->groupBy('subject_type', 'subject_id')
            ->select('subject_type', 'subject_id')
            ->selectRaw('MAX(created_at) AS latest')
            ->orderByDesc('latest')
            ->limit(6)
            ->get();

        return $this->presentActivitySubjects($rows, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(int $userId): array
    {
        $rows = DB::table('activity_log')
            ->where('causer_type', User::class)
            ->where('causer_id', (string) $userId)
            ->whereIn('subject_type', array_values(self::ENTITY_TYPES))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['event', 'subject_type', 'subject_id', 'created_at']);

        return $this->presentActivitySubjects($rows, false);
    }

    /**
     * Hydrate activity rows (subject refs only) with titles, slugs and
     * completeness. Rows whose subject no longer exists are dropped.
     *
     * @param  Collection<int, \stdClass>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function presentActivitySubjects(Collection $rows, bool $withUpdated): array
    {
        $idsByType = [];
        foreach ($rows as $row) {
            $entityType = $this->entityKeyFor($row->subject_type);
            if ($entityType !== null) {
                $idsByType[$entityType][] = (int) $row->subject_id;
            }
        }

        $records = [];
        $completeness = [];
        foreach ($idsByType as $entityType => $ids) {
            $records[$entityType] = $this->recordsFor($entityType, $ids);
            $completeness[$entityType] = $this->completenessFor($entityType, $ids);
        }

        $items = [];
        foreach ($rows as $row) {
            $entityType = $this->entityKeyFor($row->subject_type);
            if ($entityType === null) {
                continue;
            }
            $id = (int) $row->subject_id;
            $record = $records[$entityType][$id] ?? null;
            if ($record === null) {
                continue;
            }

            $item = [
                'entity_type' => $entityType,
                'id' => $id,
                'slug' => $record['slug'],
                'title' => $record['title'],
            ];

            if ($withUpdated) {
                $c = $completeness[$entityType][$id] ?? null;
                $item['completeness_pct'] = $c !== null ? (int) $c->completeness_pct : null;
                $item['updated_at'] = isset($row->latest)
                    ? Carbon::parse($row->latest)->toIso8601String()
                    : $record['updated_at'];
            } else {
                $item['event'] = $row->event;
                $item['created_at'] = Carbon::parse($row->created_at)->toIso8601String();
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return Collection<string, int>
     */
    private function proposalCounts(int $userId): Collection
    {
        return DB::table('edit_proposals')
            ->where('proposed_by_user_id', $userId)
            ->groupBy('status')
            ->pluck(DB::raw('COUNT(*)'), 'status');
    }

    private function countDraftRecords(int $userId): int
    {
        $total = 0;
        foreach (self::ENTITY_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $query = DB::table($table);
            $this->scopeMine($query, $entityType, $userId);
            $total += (int) $query->where("{$table}.publication_status", 'draft')
                ->whereNotExists(function (Builder $sub) use ($table, $modelClass, $userId) {
                    $sub->select(DB::raw(1))->from('edit_proposals')
                        ->whereColumn('edit_proposals.citable_id', "{$table}.id")
                        ->where('edit_proposals.citable_type', $modelClass)
                        ->where('edit_proposals.proposed_by_user_id', $userId)
                        ->whereIn('edit_proposals.status', ['draft', 'pending', 'changes_requested']);
                })
                ->count();
        }

        return $total;
    }

    private function countPublishedRecords(int $userId): int
    {
        $total = 0;
        foreach (self::ENTITY_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $query = DB::table($table);
            $this->scopeMine($query, $entityType, $userId);
            $total += (int) $query->where("{$table}.publication_status", 'published')->count();
        }

        return $total;
    }

    private function countUnderReview(int $userId): int
    {
        $total = 0;
        foreach (self::ENTITY_TYPES as $entityType => $modelClass) {
            $table = self::TABLES[$entityType];
            $query = DB::table($table);
            $this->scopeMine($query, $entityType, $userId);
            $total += (int) $query->whereExists(function (Builder $sub) use ($table, $modelClass) {
                $sub->select(DB::raw(1))->from('review_queue_items')
                    ->whereColumn('review_queue_items.citable_id', "{$table}.id")
                    ->where('review_queue_items.citable_type', $modelClass)
                    ->where('review_queue_items.status', 'pending');
            })->count();
        }

        return $total;
    }

    /**
     * Restrict a query on one of the four record tables to the caller's own
     * records: artists they are assigned to or created, artworks and archive
     * items they created, events they created (tracked via activity_log,
     * the same technique AdminArchiveItemIndexController uses).
     */
    private function scopeMine(Builder $query, string $entityType, int $userId): void
    {
        $table = self::TABLES[$entityType];

        if ($entityType === 'artist') {
            $query->where(function (Builder $q) use ($table, $userId) {
                $q->where("{$table}.assigned_to_user_id", $userId)
                    ->orWhere("{$table}.created_by_user_id", $userId);
            });

            return;
        }

        if ($entityType === 'event') {
            $query->whereIn("{$table}.id", $this->eventsCreatedBy($userId));

            return;
        }

        $query->where("{$table}.created_by_user_id", $userId);
    }

    private function eventsCreatedBy(int $userId): Builder
    {
        return DB::table('activity_log')->select('subject_id')
            ->where('subject_type', Event::class)
            ->where('event', 'created')
            ->where('causer_type', User::class)
            ->where('causer_id', (string) $userId);
    }

    private function joinCompleteness(Builder $query, string $entityType, string $table): void
    {
        $query->leftJoin('record_completeness as rc', function ($join) use ($entityType, $table) {
            $join->on('rc.citable_id', '=', "{$table}.id")
                ->where('rc.citable_type', '=', self::ENTITY_TYPES[$entityType]);
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, slug: string|null, title: array{ar: string|null, en: string|null}, updated_at: string}>
     */
    private function recordsFor(string $entityType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $table = self::TABLES[$entityType];
        $rows = DB::table($table)->whereIn("{$table}.id", $ids)->get($this->titleColumns($entityType));

        $records = [];
        foreach ($rows as $row) {
            $records[(int) $row->id] = [
                'id' => (int) $row->id,
                'slug' => $row->slug ?? null,
                'title' => $this->titleFromRow($entityType, $row),
                'updated_at' => Carbon::parse($row->updated_at)->toIso8601String(),
            ];
        }

        return $records;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, object>
     */
    private function completenessFor(string $entityType, array $ids): array
    {
        if ($ids === [] || ! isset(self::CITABLE_TYPES[$entityType])) {
            return [];
        }

        return DB::table('record_completeness')
            ->where('citable_type', self::ENTITY_TYPES[$entityType])
            ->whereIn('citable_id', $ids)
            ->get(['citable_id', 'completeness_pct', 'severity'])
            ->keyBy(fn ($row) => (int) $row->citable_id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function titleColumns(string $entityType): array
    {
        $table = self::TABLES[$entityType];
        $columns = ["{$table}.id", "{$table}.updated_at"];

        if ($entityType === 'artist') {
            return array_merge($columns, ["{$table}.name_ar", "{$table}.name_en", "{$table}.slug"]);
        }

        return array_merge($columns, ["{$table}.title_ar", "{$table}.title_en"]);
    }

    /**
     * @return array{ar: string|null, en: string|null}
     */
    private function titleFromRow(string $entityType, object $row): array
    {
        if ($entityType === 'artist') {
            return ['ar' => $row->name_ar, 'en' => $row->name_en];
        }

        return ['ar' => $row->title_ar, 'en' => $row->title_en];
    }

    /**
     * @param  array<string, bool>  $skip
     * @return array<int, int>
     */
    private function skipIdsFor(string $entityType, array $skip): array
    {
        $prefix = "{$entityType}:";

        return collect(array_keys($skip))
            ->filter(fn (string $key) => str_starts_with($key, $prefix))
            ->map(fn (string $key) => (int) substr($key, strlen($prefix)))
            ->values()
            ->all();
    }

    private function entityKeyFor(string $modelClass): ?string
    {
        $key = array_search($modelClass, self::ENTITY_TYPES, true);

        return is_string($key) ? $key : null;
    }
}
