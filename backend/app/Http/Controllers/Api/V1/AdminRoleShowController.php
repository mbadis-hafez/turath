<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class AdminRoleShowController
{
    public function __invoke(Role $role): JsonResponse
    {
        $role->loadCount(['users', 'permissions'])->load('permissions');

        return (new RoleResource($role))->response();
    }
}
