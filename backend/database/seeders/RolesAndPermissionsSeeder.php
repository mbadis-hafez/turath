<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const ROLES = [
        'reader',
        'contributor',
        'verified_researcher',
        'institution',
        'artist_claimed',
        'editor',
        'admin',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $activityView = Permission::findOrCreate('activity.view');

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName);
        }

        Role::findByName('editor')->givePermissionTo($activityView);
        Role::findByName('admin')->givePermissionTo($activityView);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
