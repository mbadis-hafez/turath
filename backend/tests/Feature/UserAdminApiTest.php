<?php

use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('requires authentication and the users.manage permission', function () {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();

    $this->actingAs(makeUser('reader'))->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAs(editorUser())->getJson('/api/v1/admin/users')->assertForbidden();

    $this->actingAs(makeUser('admin'))->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs(makeUser('superadmin'))->getJson('/api/v1/admin/users')->assertOk();
});

it('returns the documented shape with roles and no flattened permissions', function () {
    $admin = makeUser('admin');
    $reader = makeUser('reader');

    $this->actingAs($admin)->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'roles'],
            ],
            'links', 'meta',
        ])
        ->assertJsonPath('meta.per_page', 24)
        ->assertJsonPath('data.0.id', $admin->id)
        ->assertJsonPath('data.0.roles', ['admin'])
        ->assertJsonPath('data.1.id', $reader->id)
        ->assertJsonPath('data.1.roles', ['reader'])
        ->assertJsonMissingPath('data.0.permissions');
});

it('searches by name and email', function () {
    $admin = makeUser('admin');
    $target = User::factory()->create(['name' => 'Samar Hafez', 'email' => 'samar@example.com']);
    $other = User::factory()->create(['name' => 'Someone Else', 'email' => 'other@example.com']);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?search=Samar')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?search=other@example.com')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $other->id);
});

it('filters by role and returns nothing for an unused but valid role', function () {
    $admin = makeUser('admin');
    $reader = makeUser('reader');
    $contributor = makeUser('contributor');

    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=reader')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reader->id);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=contributor')
        ->assertOk()
        ->assertJsonPath('data.0.id', $contributor->id);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=institution')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects an unknown role', function () {
    $this->actingAs(makeUser('admin'))->getJson('/api/v1/admin/users?role=nonsense')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('respects per_page', function () {
    $admin = makeUser('admin');
    User::factory()->count(30)->create();

    $this->actingAs($admin)->getJson('/api/v1/admin/users')
        ->assertJsonCount(24, 'data')
        ->assertJsonPath('meta.per_page', 24)
        ->assertJsonPath('meta.total', 31);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?per_page=10')
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.per_page', 10);
});

it('lets an admin create a verified user with a single role', function () {
    $admin = makeUser('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Samar Hafez',
        'email' => 'samar@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'editor',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Samar Hafez')
        ->assertJsonPath('data.email', 'samar@example.com')
        ->assertJsonPath('data.roles', ['editor'])
        ->assertJsonPath('data.is_active', true);

    $user = User::where('email', 'samar@example.com')->firstOrFail();
    expect($user->hasRole('editor'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('rejects invalid create payloads and forbids readers from creating users', function () {
    $admin = makeUser('admin');
    $reader = makeUser('reader');

    $valid = [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'reader',
    ];

    // Duplicate email
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [...$valid, 'email' => $admin->email])
        ->assertUnprocessable()->assertJsonValidationErrors(['email']);

    // Unknown role
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [...$valid, 'role' => 'nonsense'])
        ->assertUnprocessable()->assertJsonValidationErrors(['role']);

    // Short password
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [...$valid, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors(['password']);

    // Reader cannot create
    $this->actingAs($reader)->postJson('/api/v1/admin/users', $valid)->assertForbidden();
});

it('shows a single user with roles and is_active to admins only', function () {
    $admin = makeUser('admin');
    $target = makeUser('editor');

    $this->actingAs($admin)->getJson("/api/v1/admin/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.roles', ['editor'])
        ->assertJsonPath('data.is_active', true);

    $this->actingAs(makeUser('reader'))->getJson("/api/v1/admin/users/{$target->id}")
        ->assertForbidden();
});

it('lets an admin update name, email, role, password and is_active', function () {
    $admin = makeUser('admin');
    $target = makeUser('editor');

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", [
        'name' => 'Renamed',
        'email' => 'renamed@example.com',
        'role' => 'reviewer',
        'password' => 'new-secret-password',
        'password_confirmation' => 'new-secret-password',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.name', 'Renamed');

    $target->refresh();
    expect($target->name)->toBe('Renamed')
        ->and($target->email)->toBe('renamed@example.com')
        ->and($target->hasRole('reviewer'))->toBeTrue()
        ->and($target->hasRole('editor'))->toBeFalse()
        ->and($target->is_active)->toBeFalse()
        ->and(Hash::check('new-secret-password', $target->password))->toBeTrue();
});

it('lets an admin keep their own email and skip the password on update', function () {
    $admin = makeUser('admin');
    $target = makeUser('editor');
    $oldHash = $target->password;

    // Unique rule ignores the user being edited.
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", ['email' => $target->email])
        ->assertOk();

    // Blank password leaves the hash untouched.
    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$target->id}", [
        'password' => null,
        'is_active' => true,
    ])->assertOk();

    $target->refresh();
    expect($target->password)->toBe($oldHash)->and($target->is_active)->toBeTrue();
});

it('refuses self-edits and reader updates', function () {
    $admin = makeUser('admin');

    $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$admin->id}", ['name' => 'Self edit'])
        ->assertForbidden();

    $target = makeUser('editor');
    $this->actingAs(makeUser('reader'))->patchJson("/api/v1/admin/users/{$target->id}", ['name' => 'Nope'])
        ->assertForbidden();
});

it('blocks login for inactive users but lets active ones in', function () {
    $inactive = User::factory()->create(['is_active' => false]);
    $headers = ['Origin' => 'http://localhost'];

    $this->postJson('/api/v1/auth/login', [
        'email' => $inactive->email,
        'password' => 'password',
    ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $active = User::factory()->create(['is_active' => true]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $active->email,
        'password' => 'password',
    ], $headers)->assertOk()->assertJsonPath('data.id', $active->id);
});

it('sends invitation mail with the credentials and flags first-login password change', function () {
    Mail::fake();
    $admin = makeUser('admin');

    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Invited',
        'email' => 'invited@example.com',
        'password' => 'temp-password',
        'password_confirmation' => 'temp-password',
        'role' => 'reader',
        'send_invitation' => true,
    ])->assertCreated()->assertJsonPath('data.must_change_password', true);

    $user = User::where('email', 'invited@example.com')->firstOrFail();
    expect($user->must_change_password)->toBeTrue();

    Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->hasTo('invited@example.com')
        && str_contains($mail->render(), 'temp-password')
        && str_contains($mail->render(), 'invited@example.com'));
});

it('creates users without invitation when the flag is absent', function () {
    Mail::fake();
    $admin = makeUser('admin');

    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Plain',
        'email' => 'plain@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'reader',
    ])->assertCreated()->assertJsonPath('data.must_change_password', false);

    Mail::assertNothingSent();
    expect(User::where('email', 'plain@example.com')->firstOrFail()->must_change_password)->toBeFalse();
});

it('locks flagged users out of everything but their profile, password change, and logout', function () {
    $flagged = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($flagged)->getJson('/api/v1/admin/users')->assertStatus(423);
    $this->actingAs($flagged)->getJson('/api/v1/activity')->assertStatus(423);
    $this->actingAs($flagged)->getJson('/api/v1/auth/user')->assertOk();

    $this->actingAs($flagged)->postJson('/api/v1/auth/password', [
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk()->assertJsonPath('data.must_change_password', false);

    $flagged->refresh();
    expect($flagged->must_change_password)->toBeFalse()
        ->and(Hash::check('brand-new-password', $flagged->password))->toBeTrue();

    // Unblocked once the password is changed.
    $this->actingAs($flagged)->getJson('/api/v1/auth/user')->assertOk();
});

it('lets an admin soft-delete a user, who then cannot log in or be listed', function () {
    $admin = makeUser('admin');
    $target = makeUser('editor');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$target->id}")->assertNoContent();

    expect(User::withTrashed()->find($target->id))->not->toBeNull()
        ->and(User::find($target->id))->toBeNull();

    // Gone from the directory.
    $this->actingAs($admin)->getJson('/api/v1/admin/users?search='.urlencode($target->email))
        ->assertOk()->assertJsonCount(0, 'data');

    // Login is refused.
    $this->postJson('/api/v1/auth/login', [
        'email' => $target->email,
        'password' => 'password',
    ], ['Origin' => 'http://localhost'])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    // Re-adding the email revives the same account with the new details.
    $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Replacement',
        'email' => $target->email,
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'role' => 'reader',
    ])->assertCreated()->assertJsonPath('data.id', $target->id);

    $target->refresh();
    expect($target->trashed())->toBeFalse()
        ->and($target->name)->toBe('Replacement')
        ->and($target->hasRole('reader'))->toBeTrue()
        ->and(Hash::check('secret-password', $target->password))->toBeTrue();
});

it('refuses to delete yourself, a superadmin, or anything for readers', function () {
    $admin = makeUser('admin');
    $superadmin = makeUser('superadmin');
    $reader = makeUser('reader');
    $target = makeUser('editor');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$admin->id}")->assertForbidden();
    $this->actingAs($admin)->deleteJson("/api/v1/admin/users/{$superadmin->id}")->assertForbidden();
    $this->actingAs($reader)->deleteJson("/api/v1/admin/users/{$target->id}")->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

it('validates the forced password change', function () {
    $flagged = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($flagged)->postJson('/api/v1/auth/password', [
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->actingAs($flagged)->postJson('/api/v1/auth/password', [
        'password' => 'long-enough-pass',
        'password_confirmation' => 'different-pass',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $flagged->refresh();
    expect($flagged->must_change_password)->toBeTrue();
});
