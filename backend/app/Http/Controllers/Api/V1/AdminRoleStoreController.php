<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Access\RoleGuard;
use App\Support\Access\RoleSlugGenerator;
use Illuminate\Http\JsonResponse;

class AdminRoleStoreController
{
    public function __construct(private readonly RoleGuard $guard = new RoleGuard) {}

    public function __invoke(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissions = $data['permissions'] ?? [];

        $this->guard->assertGrantable($request->user(), $permissions);

        // guard_name is explicit, not left to Spatie's auto-detection: Sanctum's
        // auth:sanctum middleware calls Auth::shouldUse('sanctum'), which mutates
        // auth.defaults.guard for the rest of the request. Role::create() (a
        // static call) falls through to that raw config value when nothing else
        // specifies a guard, so a role created here without this would silently
        // save guard_name=sanctum instead of web — invisible to every hasRole()/
        // can() check in the app, which all check guard web.
        $role = Role::create([
            'name' => RoleSlugGenerator::generate($data['name_en']),
            'guard_name' => 'web',
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'] ?? null,
            'description_en' => $data['description_en'] ?? null,
            'is_built_in' => false,
        ]);

        $role->syncPermissions($permissions);
        $role->loadCount(['users', 'permissions'])->load('permissions');

        return (new RoleResource($role))->response()->setStatusCode(201);
    }
}
