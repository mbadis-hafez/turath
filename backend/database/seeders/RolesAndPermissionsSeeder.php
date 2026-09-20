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
        $artistsManage = Permission::findOrCreate('artists.manage');
        $artistsVerify = Permission::findOrCreate('artists.verify');
        $holdersManage = Permission::findOrCreate('holders.manage');
        $artworksManage = Permission::findOrCreate('artworks.manage');
        $archiveManage = Permission::findOrCreate('archive.manage');
        $archivePublish = Permission::findOrCreate('archive.publish');
        $importsManage = Permission::findOrCreate('imports.manage');

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName);
        }

        Role::findByName('editor')->givePermissionTo($activityView, $artistsManage, $artistsVerify, $holdersManage, $artworksManage, $archiveManage, $archivePublish, $importsManage);
        Role::findByName('admin')->givePermissionTo($activityView, $artistsManage, $artistsVerify, $holdersManage, $artworksManage, $archiveManage, $archivePublish, $importsManage);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
