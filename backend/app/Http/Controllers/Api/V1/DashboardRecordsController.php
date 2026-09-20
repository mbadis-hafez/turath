<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Completeness\DashboardRecordsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardRecordsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $canManage = $request->user()?->can('dashboard.manage') ?? false;
        $targetUserId = $request->input('user_id');

        if ($targetUserId !== null && (int) $targetUserId !== $request->user()->id) {
            abort_unless($canManage, 403);
        }

        $userId = $targetUserId !== null ? (int) $targetUserId : $request->user()->id;

        $entityTypes = (array) $request->input('entity_type', array_keys(DashboardRecordsCollector::ENTITY_TYPES));
        $severities = (array) $request->input('severity', []);

        $sorted = (new DashboardRecordsCollector)->collect($userId, $entityTypes, $severities);

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
