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
        $eventsManage = Permission::findOrCreate('events.manage');
        $archiveManage = Permission::findOrCreate('archive.manage');
        $archivePublish = Permission::findOrCreate('archive.publish');
        $importsManage = Permission::findOrCreate('imports.manage');
        $dashboardManage = Permission::findOrCreate('dashboard.manage');
        $sourceConflictsResolve = Permission::findOrCreate('source_conflicts.resolve');
        $reviewArchivist = Permission::findOrCreate('review_queue.archivist_review');
        $reviewDataAudit = Permission::findOrCreate('review_queue.data_audit');
        $reviewSecondSource = Permission::findOrCreate('review_queue.second_source_needed');

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName);
        }

        $editorPermissions = [
            $activityView, $artistsManage, $artistsVerify, $holdersManage, $artworksManage, $eventsManage,
            $archiveManage, $archivePublish, $importsManage, $dashboardManage,
            $sourceConflictsResolve, $reviewArchivist, $reviewDataAudit, $reviewSecondSource,
        ];

        Role::findByName('editor')->givePermissionTo($editorPermissions);
        Role::findByName('admin')->givePermissionTo($editorPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
