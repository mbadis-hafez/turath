<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Role;
use App\Support\Access\RoleGuard;
use Illuminate\Http\JsonResponse;

class AdminRoleDestroyController
{
    public function __construct(private readonly RoleGuard $guard = new RoleGuard) {}

    public function __invoke(Role $role): JsonResponse
    {
        $this->guard->assertDeletable($role);

        $usersCount = $role->users()->count();
        abort_if($usersCount > 0, 409, "This role is held by {$usersCount} user(s). Move them to another role before deleting it.");

        $role->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}
