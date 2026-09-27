<?php

use App\Models\Artwork;
use App\Models\Role;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

// --- US1: visibility -------------------------------------------------------

it('lists every role with display names, built-in flag and counts', function () {
    $admin = makeUser('admin');

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles')->assertOk();

    $editor = collect($response->json('data'))->firstWhere('name', 'editor');
    expect($editor['display_name'])->toBe(['ar' => 'محرر', 'en' => 'Editor'])
        ->and($editor['is_built_in'])->toBeTrue()
        ->and($editor)->toHaveKeys(['users_count', 'permissions_count']);

    // The acting admin's own role now holds at least one user (themselves).
    $adminRow = collect($response->json('data'))->firstWhere('name', 'admin');
    expect($adminRow['users_count'])->toBeGreaterThanOrEqual(1);
});

it('shows one role with its full permission list and an updated_at for concurrency', function () {
    $admin = makeUser('admin');
    $editor = Role::where('name', 'editor')->firstOrFail();

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/roles/{$editor->id}")->assertOk();

    expect($response->json('data.permissions'))->toContain('artworks.manage')
        ->and($response->json('data.updated_at'))->not->toBeNull();
});

it('returns the permission catalogue grouped, with only the caller\'s own permissions marked grantable', function () {
    seedRoles();
    // No built-in role holds roles.manage without also holding every other
    // permission (admin) or holding neither (reviewer), so a narrow role is
    // built directly to exercise the partial-grantable case.
    $narrow = Role::create(['name' => 'narrow_test_role', 'guard_name' => 'web', 'is_built_in' => false]);
    $narrow->givePermissionTo(['roles.manage', 'activity.view']);
    $limitedAdmin = User::factory()->create();
    $limitedAdmin->assignRole('narrow_test_role');

    $response = $this->actingAs($limitedAdmin)->getJson('/api/v1/admin/permissions')->assertOk();

    $groups = collect($response->json('data'));
    expect($groups->pluck('group'))->toContain('artworks');

    $grantable = $response->json('meta.grantable');
    expect($grantable)->toContain('activity.view')
        ->and($grantable)->not->toContain('artworks.manage');
});

it('refuses roles and permissions visibility to a user without roles.manage or users.manage', function () {
    $reader = makeUser('reader');

    $this->actingAs($reader)->getJson('/api/v1/admin/roles')->assertForbidden();
    $this->actingAs(makeUser('editor'))->getJson('/api/v1/admin/permissions')->assertForbidden();
});

it('lets a users.manage holder without roles.manage read the roles list, for the role picker', function () {
    // editor holds neither users.manage nor roles.manage today; reviewer holds neither either.
    // admin holds both. Assert the index specifically honours users.manage alone by using a
    // custom role that grants only users.manage.
    $admin = makeUser('admin');
    $onlyUsersManage = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'مدير حسابات', 'name_en' => 'Account manager', 'permissions' => ['users.manage'],
    ])->json('data');

    $accountManager = User::factory()->create();
    $accountManager->assignRole($onlyUsersManage['name']);

    $this->actingAs($accountManager)->getJson('/api/v1/admin/roles')->assertOk();
    $this->actingAs($accountManager)->getJson('/api/v1/admin/permissions')->assertForbidden();
});

// --- US2: creating and deleting custom roles --------------------------------

it('creates a custom role with exactly the requested permissions and a derived slug', function () {
    $admin = makeUser('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'أمين أرشيف', 'name_en' => 'Archivist',
        'permissions' => ['review_queue.archivist_review', 'activity.view'],
    ])->assertCreated();

    expect($response->json('data.name'))->toBe('archivist')
        ->and($response->json('data.is_built_in'))->toBeFalse()
        ->and($response->json('data.permissions'))->toEqualCanonicalizing(['review_queue.archivist_review', 'activity.view']);
});

it('rejects role creation with missing names, an over-long name, a duplicate name, or an unknown permission', function () {
    $admin = makeUser('admin');

    $this->actingAs($admin)->postJson('/api/v1/admin/roles', ['permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['name_ar', 'name_en']);

    $this->actingAs($admin)->postJson('/api/v1/admin/roles', ['name_ar' => 'x', 'name_en' => str_repeat('a', 81)])
        ->assertUnprocessable()->assertJsonValidationErrors(['name_en']);

    $this->actingAs($admin)->postJson('/api/v1/admin/roles', ['name_ar' => 'محرر جديد', 'name_en' => 'Editor'])
        ->assertUnprocessable()->assertJsonValidationErrors(['name_en']);

    $this->actingAs($admin)->postJson('/api/v1/admin/roles', ['name_ar' => 'س', 'name_en' => 'Something', 'permissions' => ['not.a.real.permission']])
        ->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);
});

it('refuses to create or edit a role granting a permission the caller does not hold', function () {
    seedRoles();
    // Holds roles.manage (so the request reaches validation) but not artworks.manage.
    $narrow = Role::create(['name' => 'narrow_creator_role', 'guard_name' => 'web', 'is_built_in' => false]);
    $narrow->givePermissionTo(['roles.manage']);
    $limitedAdmin = User::factory()->create();
    $limitedAdmin->assignRole('narrow_creator_role');

    $this->actingAs($limitedAdmin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'س', 'name_en' => 'Escalated', 'permissions' => ['artworks.manage'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['permissions']);

    expect(Role::where('name', 'escalated')->exists())->toBeFalse();
});

it('refuses to rename, redescribe or delete a built-in role, for both admin and superadmin', function () {
    seedRoles();
    $editor = Role::where('name', 'editor')->firstOrFail();

    foreach ([makeUser('admin'), makeUser('superadmin')] as $actor) {
        $this->actingAs($actor)->patchJson("/api/v1/admin/roles/{$editor->id}", ['name_en' => 'Renamed'])->assertStatus(409);
        $this->actingAs($actor)->deleteJson("/api/v1/admin/roles/{$editor->id}")->assertStatus(409);
    }

    expect(Role::where('name', 'editor')->first()->name_en)->toBe('Editor')
        ->and(Role::where('name', 'editor')->exists())->toBeTrue();
});

it('lets a built-in role\'s permissions be adjusted, unlike its name or existence (reviewer, per the report that its permissions could not be edited)', function () {
    seedRoles();
    $reviewer = Role::where('name', 'reviewer')->firstOrFail();
    $before = $reviewer->permissions->pluck('name')->all();
    expect($before)->not->toContain('users.manage');

    $admin = makeUser('admin');
    $response = $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$reviewer->id}", [
        'permissions' => [...$before, 'users.manage'],
    ])->assertOk();

    expect($response->json('data.permissions_editable'))->toBeTrue()
        ->and($response->json('data.is_built_in'))->toBeTrue()
        ->and(Role::where('name', 'reviewer')->first()->hasPermissionTo('users.manage'))->toBeTrue();
});

it('keeps superadmin fully locked: no rename, no permission change, no deletion, even for a superadmin actor', function () {
    seedRoles();
    $superadmin = Role::where('name', 'superadmin')->firstOrFail();
    $actor = makeUser('superadmin');

    $this->actingAs($actor)->patchJson("/api/v1/admin/roles/{$superadmin->id}", ['name_en' => 'Renamed'])->assertStatus(409);
    $this->actingAs($actor)->patchJson("/api/v1/admin/roles/{$superadmin->id}", ['permissions' => ['activity.view']])->assertStatus(409);
    $this->actingAs($actor)->deleteJson("/api/v1/admin/roles/{$superadmin->id}")->assertStatus(409);

    $show = $this->actingAs($actor)->getJson("/api/v1/admin/roles/{$superadmin->id}")->assertOk();
    expect($show->json('data.permissions_editable'))->toBeFalse();
});

it('deletes an unheld custom role, and refuses to delete one still held by users', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'مؤقت', 'name_en' => 'Temporary', 'permissions' => [],
    ])->json('data');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$role['id']}")->assertOk();
    expect(Role::where('name', 'temporary')->exists())->toBeFalse();

    $held = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'محتفظ به', 'name_en' => 'Held', 'permissions' => [],
    ])->json('data');
    $holder = User::factory()->create();
    $holder->assignRole($held['name']);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$held['id']}")
        ->assertStatus(409)
        ->assertSee('1', false);
    expect(Role::where('name', 'held')->exists())->toBeTrue();
});

it('lets a user holding a custom role with artworks.manage appear in the staff picker', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'أمين أرشيف', 'name_en' => 'Archivist Picker Test', 'permissions' => ['artworks.manage'],
    ])->json('data');

    $archivist = User::factory()->create(['name' => 'Archivist Person', 'is_active' => true]);
    $archivist->assignRole($role['name']);

    $this->actingAs($admin)->getJson('/api/v1/staff-options')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Archivist Person']);
});

it('lets a newly created custom role be assigned to a user via the existing user-update endpoint', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'دور جديد', 'name_en' => 'Brand New Role', 'permissions' => [],
    ])->json('data');

    $target = User::factory()->create();

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", ['role' => $role['name']])->assertOk();
    expect($target->fresh()->hasRole($role['name']))->toBeTrue();
});

// --- US3: adjusting a custom role -------------------------------------------

it('syncs permissions as a complete set and changes what holders can do', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'قابل للتعديل', 'name_en' => 'Adjustable', 'permissions' => ['activity.view'],
    ])->json('data');
    $holder = User::factory()->create();
    $holder->assignRole($role['name']);

    expect($holder->fresh()->can('artworks.manage'))->toBeFalse();

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['permissions' => ['artworks.manage']])->assertOk();

    expect($holder->fresh()->can('artworks.manage'))->toBeTrue()
        ->and($holder->fresh()->can('activity.view'))->toBeFalse();
});

it('renames a custom role without changing its immutable identifier or interrupting holders', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'اسم أول', 'name_en' => 'First Name', 'permissions' => ['artworks.manage'],
    ])->json('data');
    $holder = User::factory()->create();
    $holder->assignRole($role['name']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['name_en' => 'Second Name'])->assertOk();

    $fresh = Role::find($role['id']);
    expect($fresh->name)->toBe($role['name'])
        ->and($fresh->name_en)->toBe('Second Name')
        ->and($holder->fresh()->can('artworks.manage'))->toBeTrue();
});

it('refuses self-lockout: an administrator cannot strip roles.manage from their own custom role', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'دوري', 'name_en' => 'My Own Role', 'permissions' => ['roles.manage'],
    ])->json('data');
    $admin->syncRoles([$role['name']]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['permissions']);

    expect($admin->fresh()->can('roles.manage'))->toBeTrue();
});

it('refuses a change that would leave zero active users able to administer access', function () {
    $superadmin = makeUser('superadmin');
    // Deactivate every other admin-capable account so this custom role is the last one standing.
    User::whereKeyNot($superadmin->id)->update(['is_active' => false]);
    $role = $this->actingAs($superadmin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'آخر مدير', 'name_en' => 'Last Admin Role', 'permissions' => ['roles.manage'],
    ])->json('data');
    $lastAdmin = User::factory()->create(['is_active' => true]);
    $lastAdmin->assignRole($role['name']);
    $superadmin->update(['is_active' => false]); // superadmin no longer counts

    $this->actingAs($lastAdmin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors(['permissions']);
});

it('refuses a save whose updated_at precondition is stale, without discarding the concurrent change', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'س', 'name_en' => 'Concurrent', 'permissions' => ['activity.view'],
    ])->json('data');
    $staleTimestamp = $role['updated_at'];

    // A real gap so the concurrent save's updated_at is provably different —
    // both requests otherwise complete within the same DB-timestamp second.
    $this->travel(2)->seconds();

    // Someone else's change lands first.
    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['name_en' => 'Changed First'])->assertOk();

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", [
        'name_en' => 'Overwrite Attempt', 'updated_at' => $staleTimestamp,
    ])->assertStatus(409);

    expect(Role::find($role['id'])->name_en)->toBe('Changed First');
});

// --- US4: accountability ----------------------------------------------------

it('logs a role rename with the causer and the old/new values', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'قبل', 'name_en' => 'Before Name', 'permissions' => [],
    ])->json('data');

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['name_en' => 'After Name'])->assertOk();

    $entry = Activity::where('subject_type', Role::class)->where('subject_id', $role['id'])
        ->where('event', 'updated')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe((string) $admin->id)
        ->and($entry->attribute_changes['attributes']['name_en'] ?? null)->toBe('After Name');
});

it('logs a permission change naming what was added and removed, since it touches no role attribute', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'س', 'name_en' => 'Audited Role', 'permissions' => ['activity.view'],
    ])->json('data');

    $this->actingAs($admin)->patchJson("/api/v1/admin/roles/{$role['id']}", ['permissions' => ['artworks.manage']])->assertOk();

    $entry = Activity::where('subject_type', Role::class)->where('subject_id', $role['id'])
        ->where('description', 'permissions changed')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe((string) $admin->id)
        ->and($entry->properties['permissions_added'])->toBe(['artworks.manage'])
        ->and($entry->properties['permissions_removed'])->toBe(['activity.view']);
});

it('logs role creation and deletion, visible only to activity.view holders', function () {
    $admin = makeUser('admin');
    $role = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'س', 'name_en' => 'Lifecycle Role', 'permissions' => [],
    ])->json('data');
    $this->actingAs($admin)->deleteJson("/api/v1/admin/roles/{$role['id']}")->assertOk();

    expect(Activity::where('subject_type', Role::class)->where('subject_id', $role['id'])->where('event', 'created')->exists())->toBeTrue()
        ->and(Activity::where('subject_type', Role::class)->where('subject_id', $role['id'])->where('event', 'deleted')->exists())->toBeTrue();

    // admin holds activity.view in this seeder, so exercise the negative case with a role that doesn't.
    $roleWithoutView = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'بلا اطلاع', 'name_en' => 'No Activity View', 'permissions' => ['artworks.manage'],
    ])->json('data');
    $blindUser = User::factory()->create();
    $blindUser->assignRole($roleWithoutView['name']);

    $this->actingAs($blindUser)->getJson('/api/v1/activity')->assertForbidden();
});

// --- Cross-cutting: nothing about attaching/publication changes ------------

it('never lets a role change affect an unrelated resource', function () {
    $admin = makeUser('admin');
    $artwork = Artwork::factory()->create(['publication_status' => 'draft']);

    $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
        'name_ar' => 'س', 'name_en' => 'Unrelated', 'permissions' => ['artworks.manage'],
    ])->assertCreated();

    expect($artwork->refresh()->publication_status)->toBe('draft');
});
