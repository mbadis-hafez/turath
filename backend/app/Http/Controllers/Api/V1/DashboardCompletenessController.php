<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewType;
use App\Models\RecordCompleteness;
use App\Models\ReviewQueueItem;
use App\Models\User;
use App\Models\UserDashboardStat;
use App\Support\Completeness\DashboardRecordsCollector;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCompletenessController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $canManage = $user?->can('dashboard.manage') ?? false;

        if ($request->input('scope') === 'org') {
            abort_unless($canManage, 403);

            return response()->json(['data' => $this->orgAggregate()]);
        }

        $targetUserId = $request->input('user_id');

        if ($targetUserId !== null && (int) $targetUserId !== $user->id) {
            abort_unless($canManage, 403);
        }

        $userId = $targetUserId !== null ? (int) $targetUserId : $user->id;

        $stats = UserDashboardStat::query()->find($userId);

        if ($stats === null) {
            (new DashboardStatsCalculator)->recompute($userId);
            $stats = UserDashboardStat::query()->find($userId);
        }

        $statsUser = User::find($userId);

        $own = [
            'user' => $statsUser ? ['id' => $statsUser->id, 'name' => $statsUser->name] : null,
            'total_records' => $stats->total_records ?? 0,
            'avg_completeness_pct' => $stats->avg_completeness_pct ?? 0,
            'blocking_record_count' => $stats->blocking_record_count ?? 0,
            'conflict_count' => $stats->conflict_count ?? 0,
            'missing_field_count' => $stats->missing_field_count ?? 0,
            'by_entity_type' => $stats->by_entity_type ?? [],
            'computed_at' => $stats?->computed_at->toIso8601String(),
        ];

        // Own dashboard: add in whatever's pending in the queues this user works
        // (additive with their own "records I created" stats — see DashboardRecordsController).
        // Someone else's dashboard (user_id) only ever shows what that person created.
        $reviewTypes = $targetUserId === null ? ReviewType::reviewableBy($user) : [];
        if ($reviewTypes === []) {
            return response()->json(['data' => $own]);
        }

        return response()->json(['data' => $this->mergeStats($own, $this->reviewerAggregate($reviewTypes))]);
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return array<string, mixed>
     */
    private function reviewerAggregate(array $reviewTypes): array
    {
        $totalRecords = 0;
        $pctSum = 0;
        $blockingCount = 0;
        $conflictCount = 0;
        $missingFieldCount = 0;
        $byEntityType = [];

        foreach (DashboardRecordsCollector::ENTITY_TYPES as $key => $modelClass) {
            $citableIds = ReviewQueueItem::query()
                ->where('citable_type', $modelClass)
                ->where('status', 'pending')
                ->whereIn('review_type', $reviewTypes)
                ->pluck('citable_id');

            $completeness = $citableIds->isEmpty() ? collect() : RecordCompleteness::query()
                ->where('citable_type', $modelClass)
                ->whereIn('citable_id', $citableIds)
                ->get();

            $of = $citableIds->count();
            $gapCount = $completeness->filter(fn (RecordCompleteness $c) => $c->severity !== 'clear')->count();
            $avgPct = $completeness->count() > 0 ? (int) round($completeness->avg('completeness_pct')) : 100;
            $mostCommonGap = $completeness
                ->flatMap(fn (RecordCompleteness $c) => $c->blocking_gap_field_keys ?? [])
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();

            $byEntityType[$key] = ['pct' => $avgPct, 'note_key' => $mostCommonGap, 'count' => $gapCount, 'of' => $of];

            $totalRecords += $of;
            $pctSum += $avgPct * $of;
            $blockingCount += $completeness->where('severity', 'blocking')->count();
            $conflictCount += $completeness->sum('open_conflict_count');
            $missingFieldCount += $completeness->sum(fn (RecordCompleteness $c) => count($c->blocking_gap_field_keys ?? []) + count($c->minor_gap_field_keys ?? []));
        }

        return [
            'user' => null,
            'total_records' => $totalRecords,
            'avg_completeness_pct' => $totalRecords > 0 ? (int) round($pctSum / $totalRecords) : 100,
            'blocking_record_count' => $blockingCount,
            'conflict_count' => $conflictCount,
            'missing_field_count' => $missingFieldCount,
            'by_entity_type' => $byEntityType,
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $own
     * @param  array<string, mixed>  $reviewer
     * @return array<string, mixed>
     */
    private function mergeStats(array $own, array $reviewer): array
    {
        $totalRecords = $own['total_records'] + $reviewer['total_records'];

        $byEntityType = $own['by_entity_type'];
        foreach ($reviewer['by_entity_type'] as $key => $entry) {
            $existing = $byEntityType[$key] ?? null;
            if ($existing === null) {
                $byEntityType[$key] = $entry;

                continue;
            }
            $of = $existing['of'] + $entry['of'];
            $byEntityType[$key] = [
                'pct' => $of > 0 ? (int) round((($existing['pct'] * $existing['of']) + ($entry['pct'] * $entry['of'])) / $of) : 100,
                'note_key' => $existing['note_key'] ?? $entry['note_key'],
                'count' => $existing['count'] + $entry['count'],
                'of' => $of,
            ];
        }

        return [
            'user' => $own['user'],
            'total_records' => $totalRecords,
            'avg_completeness_pct' => $totalRecords > 0
                ? (int) round((($own['avg_completeness_pct'] * $own['total_records']) + ($reviewer['avg_completeness_pct'] * $reviewer['total_records'])) / $totalRecords)
                : 100,
            'blocking_record_count' => $own['blocking_record_count'] + $reviewer['blocking_record_count'],
            'conflict_count' => $own['conflict_count'] + $reviewer['conflict_count'],
            'missing_field_count' => $own['missing_field_count'] + $reviewer['missing_field_count'],
            'by_entity_type' => $byEntityType,
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orgAggregate(): array
    {
        $all = RecordCompleteness::query()->get();

        return [
            'user' => null,
            'total_records' => $all->count(),
            'avg_completeness_pct' => $all->count() > 0 ? (int) round($all->avg('completeness_pct')) : 0,
            'blocking_record_count' => $all->where('severity', 'blocking')->count(),
            'conflict_count' => (int) $all->sum('open_conflict_count'),
            'missing_field_count' => (int) $all->sum(fn (RecordCompleteness $c) => count($c->blocking_gap_field_keys ?? []) + count($c->minor_gap_field_keys ?? [])),
            'computed_at' => now()->toIso8601String(),
        ];
    }
}
