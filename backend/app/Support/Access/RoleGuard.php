<?php

namespace App\Support\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The rules every write against a role must satisfy, consulted by every
 * write controller rather than re-implemented per action:
 *
 * 1. A built-in role's identity (name/description) and existence are immutable
 *    through the API — renaming and deleting stay code/seeder-only (FR-005, FR-015,
 *    invariant I2). Its *permissions*, however, are administrator-adjustable, same
 *    as a custom role's — the built-in/custom split protects what a role means and
 *    whether it exists, not what it's allowed to do.
 * 2. `superadmin` alone stays fully protected: no rename, no permission change, no
 *    deletion — it is the role that guarantees a way back into the system.
 * 3. An administrator cannot grant a permission they do not themselves hold (FR-024, invariant I3).
 * 4. An administrator cannot remove access administration from their own role (FR-009).
 * 5. No change may leave zero active users able to administer access (FR-010, invariant I4).
 *
 * Keyed off `is_built_in` (rules 1) and the `superadmin` slug (rule 2) rather than
 * a broader hardcoded role-name list, so protection cannot drift from what the
 * seeder actually stamps.
 */
class RoleGuard
{
    private const ACCESS_ADMIN_PERMISSION = 'roles.manage';

    private const FULLY_LOCKED_ROLE = 'superadmin';

    /** Blocks renaming or re-describing a built-in role — its identity stays code-defined. */
    public function assertAttributesMutable(Role $role): void
    {
        abort_if($role->is_built_in, 409, "This role's name and description are defined in code and cannot be changed here.");
    }

    /** Blocks permission changes only for the one role that must always stay whole: superadmin. */
    public function assertPermissionsMutable(Role $role): void
    {
        abort_if($role->name === self::FULLY_LOCKED_ROLE, 409, 'The superadmin role is fully protected; its permissions cannot be changed here.');
    }

    /** Blocks deleting any built-in role — only a custom role can be removed. */
    public function assertDeletable(Role $role): void
    {
        abort_if($role->is_built_in, 409, 'This role is defined in code and cannot be deleted.');
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function assertGrantable(User $actor, array $permissionNames): void
    {
        $held = $actor->getAllPermissions()->pluck('name')->all();
        $ungranted = array_values(array_diff($permissionNames, $held));

        if ($ungranted !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant a permission you do not hold yourself: '.implode(', ', $ungranted).'.',
            ]);
        }
    }

    /**
     * Rules 3 and 4, both about preserving the ability to administer access.
     * Call before applying a permission change to $role.
     *
     * @param  array<int, string>  $newPermissionNames  the complete intended permission set
     */
    public function assertAccessAdministrationPreserved(User $actor, Role $role, array $newPermissionNames): void
    {
        $hadAccessAdmin = $role->hasPermissionTo(self::ACCESS_ADMIN_PERMISSION);
        $keepsAccessAdmin = in_array(self::ACCESS_ADMIN_PERMISSION, $newPermissionNames, true);

        if (! $hadAccessAdmin || $keepsAccessAdmin) {
            return;
        }

        if ($actor->hasRole($role->name)) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot remove your own ability to administer access.',
            ]);
        }

        $remaining = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', '!=', $role->id))
            ->permission(self::ACCESS_ADMIN_PERMISSION)
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'permissions' => 'This change would leave no one able to administer access. Grant it to another role first.',
            ]);
        }
    }
}
