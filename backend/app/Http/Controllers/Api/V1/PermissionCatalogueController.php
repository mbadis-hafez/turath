<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The read-only permission catalogue (FR-022, invariant I5): permissions are
 * created only by code + seeder, never through this endpoint, so an
 * administrator can only ever assign an ability the product actually
 * enforces.
 */
class PermissionCatalogueController
{
    public function __invoke(Request $request): JsonResponse
    {
        $permissions = Permission::query()->orderBy('group')->orderBy('name')->get();
        $grantable = $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [];

        $groups = $permissions->groupBy(fn (Permission $p) => $p->group ?? 'other')
            ->map(fn ($group, $groupName) => [
                'group' => $groupName,
                'permissions' => PermissionResource::collection($group)->resolve(),
            ])
            ->values();

        return response()->json(['data' => $groups, 'meta' => ['grantable' => $grantable]]);
    }
}
