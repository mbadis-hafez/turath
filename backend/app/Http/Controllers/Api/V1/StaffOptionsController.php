<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A short list of staff a record can be assigned to. Anyone who can manage the
 * record type may open the picker, so this does not require `users.manage` —
 * it never exposes more than id/name/email, never roles or account status.
 */
class StaffOptionsController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = User::query()->where('is_active', true)->role(['editor', 'admin', 'superadmin', 'reviewer']);

        if ($q !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($q, 0, 100)).'%';
            $query->where(fn ($w) => $w->where('name', 'like', $needle)->orWhere('email', 'like', $needle));
        }

        return response()->json(['data' => $query->orderBy('name')->limit(15)->get(['id', 'name', 'email'])->values()]);
    }
}
