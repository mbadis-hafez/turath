<?php

namespace App\Support\Completeness;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\RecordCompleteness;
use Illuminate\Support\Facades\DB;

/**
 * Produces a user_dashboard_stats row (D53, materialized). Scoped to "my
 * records" per D52 — records the user created (see created_by_user_id,
 * added in F10's migration since F1-F3 never tracked a creator).
 */
class DashboardStatsCalculator
{
    /**
     * @var array<string, class-string>
     */
    private const ENTITY_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'archive_item' => ArchiveItem::class,
    ];

    public function recompute(int $userId): void
    {
        $totalRecords = 0;
        $pctSum = 0;
        $blockingCount = 0;
        $conflictCount = 0;
        $missingFieldCount = 0;
        $byEntityType = [];

        foreach (self::ENTITY_TYPES as $key => $modelClass) {
            $ids = $modelClass::query()->where('created_by_user_id', $userId)->pluck('id');

            $completeness = RecordCompleteness::query()
                ->where('citable_type', $modelClass)
                ->whereIn('citable_id', $ids)
                ->get();

            $of = $ids->count();
            $gapCount = $completeness->filter(fn (RecordCompleteness $c) => $c->severity !== 'clear')->count();
            $avgPct = $completeness->count() > 0 ? (int) round($completeness->avg('completeness_pct')) : 100;

            $mostCommonGap = $completeness
                ->flatMap(fn (RecordCompleteness $c) => $c->blocking_gap_field_keys ?? [])
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();

            $byEntityType[$key] = [
                'pct' => $avgPct,
                'note_key' => $mostCommonGap,
                'count' => $gapCount,
                'of' => $of,
            ];

            $totalRecords += $of;
            $pctSum += $avgPct * $of;
            $blockingCount += $completeness->where('severity', 'blocking')->count();
            $conflictCount += $completeness->sum('open_conflict_count');
            $missingFieldCount += $completeness->sum(fn (RecordCompleteness $c) => count($c->blocking_gap_field_keys ?? []) + count($c->minor_gap_field_keys ?? []));
        }

        DB::table('user_dashboard_stats')->updateOrInsert(
            ['user_id' => $userId],
            [
                'total_records' => $totalRecords,
                'avg_completeness_pct' => $totalRecords > 0 ? (int) round($pctSum / $totalRecords) : 100,
                'blocking_record_count' => $blockingCount,
                'conflict_count' => $conflictCount,
                'missing_field_count' => $missingFieldCount,
                'by_entity_type' => json_encode($byEntityType),
                'computed_at' => now(),
            ],
        );
    }
}
