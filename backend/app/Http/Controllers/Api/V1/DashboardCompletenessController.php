<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\RecordCompleteness;
use App\Models\User;
use App\Models\UserDashboardStat;
use App\Support\Completeness\DashboardStatsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCompletenessController
{
    public function __invoke(Request $request): JsonResponse
    {
        $canManage = $request->user()?->can('dashboard.manage') ?? false;

        if ($request->input('scope') === 'org') {
            abort_unless($canManage, 403);

            return response()->json(['data' => $this->orgAggregate()]);
        }

        $targetUserId = $request->input('user_id');

        if ($targetUserId !== null && (int) $targetUserId !== $request->user()->id) {
            abort_unless($canManage, 403);
        }

        $userId = $targetUserId !== null ? (int) $targetUserId : $request->user()->id;

        $stats = UserDashboardStat::query()->find($userId);

        if ($stats === null) {
            (new DashboardStatsCalculator)->recompute($userId);
            $stats = UserDashboardStat::query()->find($userId);
        }

        $user = User::find($userId);

        return response()->json(['data' => [
            'user' => $user ? ['id' => $user->id, 'name' => $user->name] : null,
            'total_records' => $stats->total_records ?? 0,
            'avg_completeness_pct' => $stats->avg_completeness_pct ?? 0,
            'blocking_record_count' => $stats->blocking_record_count ?? 0,
            'conflict_count' => $stats->conflict_count ?? 0,
            'missing_field_count' => $stats->missing_field_count ?? 0,
            'by_entity_type' => $stats->by_entity_type ?? [],
            'computed_at' => $stats?->computed_at->toIso8601String(),
        ]]);
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
