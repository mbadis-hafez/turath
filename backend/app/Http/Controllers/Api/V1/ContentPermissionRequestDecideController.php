<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContentPermissionRequest;
use App\Support\Proposals\PublishedContentGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admin-only: approve or reject a Reviewer's edit/delete request. */
class ContentPermissionRequestDecideController
{
    public function approve(Request $request, ContentPermissionRequest $permissionRequest): JsonResponse
    {
        return $this->decide($request, $permissionRequest, 'approved');
    }

    public function reject(Request $request, ContentPermissionRequest $permissionRequest): JsonResponse
    {
        return $this->decide($request, $permissionRequest, 'rejected');
    }

    private function decide(Request $request, ContentPermissionRequest $permissionRequest, string $status): JsonResponse
    {
        abort_unless(PublishedContentGuard::isAdminTier($request->user()), 403);
        abort_unless($permissionRequest->status === 'pending', 422);

        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);

        $permissionRequest->update([
            'status' => $status,
            'decided_by_user_id' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        return response()->json(['data' => ContentPermissionRequestIndexController::present($permissionRequest->refresh()->load(['requestedBy', 'decidedBy', 'citable']))]);
    }
}
