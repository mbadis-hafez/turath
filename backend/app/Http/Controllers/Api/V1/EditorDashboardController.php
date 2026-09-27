<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Dashboard\EditorDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET dashboard/editor — the editor-role dashboard. Auth-only and strictly
 * self-scoped: everything is aggregated for auth()->id() and the endpoint
 * accepts no user_id input, so it can never leak another user's records or
 * org-wide admin data. Any authenticated role (editor, reviewer, admin)
 * gets their own self-scoped view — reviewers just usually see zeros.
 *
 * review_pipeline canonical definitions:
 * - draft: my records (any of the 4 types) with publication_status=draft
 *   and no open proposal of mine (status in draft/pending/changes_requested)
 * - in_progress: my edit_proposals with status=draft
 * - ready_for_review: my edit_proposals with status=pending
 * - under_review: my records having a pending review_queue_item
 * - changes_requested: my edit_proposals with status=changes_requested
 * - approved: my edit_proposals with status=approved (informational)
 * - published: my records with publication_status=published
 *
 * "My records" scoping: artists → assigned_to_user_id = me OR
 * created_by_user_id = me; artworks/archive_items → created_by_user_id = me;
 * events → created via activity_log (event=created, causer=me), since event
 * ownership is only tracked there. record_completeness is read materialized,
 * never recalculated.
 */
class EditorDashboardController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => (new EditorDashboard)->forUser($request->user()->id),
        ]);
    }
}
