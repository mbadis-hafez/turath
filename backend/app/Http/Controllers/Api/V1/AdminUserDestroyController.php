<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminUserDestroyController
{
    public function __invoke(Request $request, User $user): JsonResponse|Response
    {
        abort_if($request->user()->id === $user->id, 403);
        abort_if($user->hasRole('superadmin'), 403, 'Superadmin accounts cannot be deleted; deactivate them instead.');

        $user->delete();

        return response()->noContent();
    }
}
