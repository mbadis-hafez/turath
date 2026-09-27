<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read by two audiences: administrators managing roles (roles.manage), and
 * the user screens' role picker (users.manage) — assigning a role to a user
 * needs the same list this feature otherwise gates behind roles.manage.
 * There is no route-level "any of" permission check, so it is done here.
 */
class AdminRoleIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('roles.manage') || $user?->can('users.manage'), 403);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_built_in')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles)->response();
    }
}
