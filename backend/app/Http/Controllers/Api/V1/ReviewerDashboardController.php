<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dashboard\ReviewerDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET dashboard/reviewer — the reviewer-role dashboard. Auth-only and
 * strictly self-scoped: everything is aggregated for auth()->id() and the
 * endpoint accepts no user_id input, so it can never leak another user's
 * queue or org-wide data. Unlike dashboard/editor (self-authorship-scoped),
 * this is scoped by which review_queue.* permissions the caller holds
 * (App\Enums\ReviewType::reviewableBy) — a user with none gets an
 * all-zero payload, never an error.
 */
class ReviewerDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new ReviewerDashboard)->forUser($request->user()),
        ]);
    }
}
