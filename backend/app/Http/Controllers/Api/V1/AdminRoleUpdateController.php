<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Access\RoleGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminRoleUpdateController
{
    public function __construct(private readonly RoleGuard $guard = new RoleGuard) {}

    public function __invoke(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();
        $attributeData = array_intersect_key($data, array_flip(['name_ar', 'name_en', 'description_ar', 'description_en']));

        if ($attributeData !== []) {
            $this->guard->assertAttributesMutable($role);
        }
        if (array_key_exists('permissions', $data)) {
            $this->guard->assertPermissionsMutable($role);
        }

        if (isset($data['updated_at']) && ! Carbon::parse($data['updated_at'])->eq($role->updated_at)) {
            throw ValidationException::withMessages([
                'updated_at' => 'This role was changed by someone else since you loaded it. Reload and try again.',
            ])->status(409);
        }

        if (array_key_exists('permissions', $data)) {
            $this->guard->assertGrantable($request->user(), $data['permissions']);
            $this->guard->assertAccessAdministrationPreserved($request->user(), $role, $data['permissions']);
            $this->applyPermissions($role, $data['permissions'], $request->input('edit_summary'));
        }

        $role->fill($attributeData);
        $role->save();

        $role->loadCount(['users', 'permissions'])->load('permissions');

        return (new RoleResource($role))->response();
    }

    /**
     * Granting/revoking permissions writes to the role_has_permissions pivot,
     * not an attribute on `roles` — LogsChanges' dirty-attribute diffing sees
     * nothing there, so the change would otherwise go entirely unaudited.
     * Written explicitly instead, naming what was added and removed.
     *
     * @param  array<int, string>  $newPermissionNames
     */
    private function applyPermissions(Role $role, array $newPermissionNames, ?string $editSummary): void
    {
        $before = $role->permissions->pluck('name')->all();
        $role->syncPermissions($newPermissionNames);
        $after = $newPermissionNames;

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return;
        }

        activity($role->getTable())->performedOn($role)->causedBy(request()->user())->event('updated')
            ->withProperties(['edit_summary' => $editSummary, 'permissions_added' => $added, 'permissions_removed' => $removed])
            ->log('permissions changed');
    }
}
