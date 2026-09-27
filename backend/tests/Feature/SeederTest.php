<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TeamUserSeeder;

it('seeds all roles and the activity view permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::pluck('name')->sort()->values()->all())->toBe([
        'admin',
        'artist_claimed',
        'contributor',
        'editor',
        'institution',
        'reader',
        'reviewer',
        'superadmin',
        'verified_researcher',
    ])->and(Permission::where('name', 'activity.view')->exists())->toBeTrue()
        ->and(Role::findByName('editor')->hasPermissionTo('activity.view'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('activity.view'))->toBeTrue()
        ->and(Role::findByName('superadmin')->hasPermissionTo('activity.view'))->toBeTrue()
        ->and(Role::findByName('reviewer')->hasPermissionTo('activity.view'))->toBeTrue()
        ->and(Role::findByName('reader')->hasPermissionTo('activity.view'))->toBeFalse();

    // Every built-in role carries bilingual display names and is flagged as
    // built-in — the single source RoleGuard uses to protect their identity
    // (renaming, deletion) via the API. Their permissions, unlike their
    // identity, are administrator-adjustable once seeded (except superadmin).
    foreach (Role::all() as $role) {
        expect($role->is_built_in)->toBeTrue()
            ->and($role->name_ar)->not->toBeNull()
            ->and($role->name_en)->not->toBeNull();
    }

    // roles.manage: what makes this whole feature's write endpoints reachable.
    expect(Permission::where('name', 'roles.manage')->exists())->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('roles.manage'))->toBeTrue()
        ->and(Role::findByName('superadmin')->hasPermissionTo('roles.manage'))->toBeTrue()
        ->and(Role::findByName('editor')->hasPermissionTo('roles.manage'))->toBeFalse();
});

it('re-running the seeder leaves a custom role untouched', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $custom = Role::create([
        'name' => 'archivist', 'guard_name' => 'web',
        'name_ar' => 'أمين أرشيف', 'name_en' => 'Archivist', 'is_built_in' => false,
    ]);
    $custom->givePermissionTo('artworks.manage');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(TeamUserSeeder::class);

    $custom->refresh();
    expect($custom->exists)->toBeTrue()
        ->and($custom->is_built_in)->toBeFalse()
        ->and($custom->permissions->pluck('name')->all())->toBe(['artworks.manage']);
});

it('re-running the seeder does not undo an administrator\'s permission change on a built-in role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $editor = Role::where('name', 'editor')->firstOrFail();
    expect($editor->hasPermissionTo('artworks.manage'))->toBeTrue();

    // An administrator revokes a baseline permission and grants one the role
    // never had — the two ways a built-in role's set can diverge from the
    // seeder's baseline once it's been edited through the API.
    $editor->revokePermissionTo('artworks.manage');
    $editor->givePermissionTo('users.manage');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(TeamUserSeeder::class);

    $fresh = $editor->fresh();
    expect($fresh->hasPermissionTo('artworks.manage'))->toBeFalse()
        ->and($fresh->hasPermissionTo('users.manage'))->toBeTrue();
});

it('creates local users only outside the testing environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(app()->environment())->toBe('testing')
        ->and(User::whereIn('email', ['admin@bidayaat.test', 'editor@bidayaat.test'])->exists())->toBeFalse();
});
