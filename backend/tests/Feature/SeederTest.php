<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
});

it('creates local users only outside the testing environment', function () {
    $this->seed(DatabaseSeeder::class);

    expect(app()->environment())->toBe('testing')
        ->and(User::whereIn('email', ['admin@bidayaat.test', 'editor@bidayaat.test'])->exists())->toBeFalse();
});
