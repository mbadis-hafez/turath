<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A short list of staff a record can be assigned to. Anyone who can manage the
 * record type may open the picker, so this does not require `users.manage` —
 * it never exposes more than id/name/email, never roles or account status.
 *
 * Filtered by permission, not role name: a custom role's holders must be
 * assignable too, or "create a role for a new kind of staff member" produces
 * staff who can't be assigned anything. `->role([...])` (the previous
 * approach) can only ever see the roles that existed when it was written.
 * The permission list below is the same surface the four original roles
 * (editor, admin, superadmin, reviewer) collectively covered — content
 * management and review-queue work — not "every permission that exists",
 * which would trivially include everyone since superadmin was one of the
 * four and already holds all of them.
 */
class StaffOptionsController
{
    private const STAFF_PERMISSIONS = [
        'artists.manage', 'holders.manage', 'artworks.manage', 'events.manage',
        'archive.manage', 'archive.publish', 'imports.manage',
        'source_conflicts.resolve', 'materials.review', 'review_queue.material_intake',
        'review_queue.editorial_review', 'review_queue.archivist_review',
        'review_queue.data_audit', 'review_queue.second_source_needed',
        'users.manage', 'roles.manage',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = User::query()->where('is_active', true)->permission(self::STAFF_PERMISSIONS);

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($q, 0, 100)).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $needle)->orWhere('email', 'like', $needle));
        }

        return response()->json(['data' => $query->orderBy('name')->limit(15)->get(['id', 'name', 'email'])->values()]);
    }
}
