<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewType;
use App\Support\Completeness\DashboardRecordsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardRecordsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $canManage = $user?->can('dashboard.manage') ?? false;
        $targetUserId = $request->input('user_id');

        if ($targetUserId !== null && (int) $targetUserId !== $user->id) {
            abort_unless($canManage, 403);
        }

        $entityTypes = (array) $request->input('entity_type', array_keys(DashboardRecordsCollector::ENTITY_TYPES));
        $severities = (array) $request->input('severity', []);
        $collector = new DashboardRecordsCollector;

        // Own dashboard: records the user created, plus (additively — a user can
        // hold both a manage permission and review_queue.* permissions) records
        // pending review in the queues they work. Someone else's dashboard
        // (user_id, requires dashboard.manage) only ever shows what that person created.
        if ($targetUserId === null) {
            $sorted = $collector->collectForUser($user->id, ReviewType::reviewableBy($user), $entityTypes, $severities);
        } else {
            $sorted = $collector->collect((int) $targetUserId, $entityTypes, $severities);
        }

        $perPage = min((int) $request->input('per_page', 24), 100);
        $page = max((int) $request->input('page', 1), 1);
        $items = $sorted->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $sorted->count(),
                'last_page' => (int) max(1, ceil($sorted->count() / $perPage)),
            ],
        ]);
    }
}
