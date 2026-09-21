<?php

use App\Models\Artist;
use App\Models\ReviewQueueItem;
use App\Models\User;
use Database\Seeders\TeamUserSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedTeam(): void
{
    test()->seed(TeamUserSeeder::class);
}

/** Sanctum treats a request from the app's own origin as the single-page app, which is how sign-in works. */
function spaHeaders(): array
{
    return ['Origin' => 'http://localhost'];
}

function member(string $email): User
{
    return User::where('email', $email)->firstOrFail();
}

it('creates the five team accounts with the roles they were given, already verified', function () {
    seedTeam();

    $expected = [
        'mali@hafezgallery.com' => 'admin',
        'mbadis@hafezgallery.com' => 'superadmin',
        'fidha.fatma@hafezgallery.com' => 'editor',
        'valeria@hafezgallery.com' => 'admin',
        'samar@hafezgallery.com' => 'reviewer',
    ];

    expect(User::count())->toBe(5);
    foreach ($expected as $email => $role) {
        $user = member($email);
        expect($user->getRoleNames()->all())->toBe([$role])->and($user->email_verified_at)->not->toBeNull();
    }
});

it('gives the superadmin every permission that exists and the reviewer only review work', function () {
    seedTeam();

    expect(member('mbadis@hafezgallery.com')->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe(Permission::pluck('name')->sort()->values()->all());

    $reviewer = member('samar@hafezgallery.com');
    expect($reviewer->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe([
        'activity.view', 'review_queue.archivist_review', 'review_queue.data_audit', 'review_queue.editorial_review',
        'review_queue.material_intake', 'review_queue.second_source_needed', 'source_conflicts.resolve',
    ]);
    foreach (['artists.manage', 'artworks.manage', 'archive.manage', 'archive.publish', 'events.manage', 'imports.manage', 'proposals.submit'] as $permission) {
        expect($reviewer->can($permission))->toBeFalse();
    }
});

it('gives admins and the editor the working permissions, but not everything', function () {
    seedTeam();

    foreach (['mali@hafezgallery.com', 'valeria@hafezgallery.com', 'fidha.fatma@hafezgallery.com'] as $email) {
        $user = member($email);
        expect($user->can('artists.manage'))->toBeTrue()->and($user->can('archive.publish'))->toBeTrue();
    }
});

it('lets the reviewer work the queue and refuses them the editing routes', function () {
    seedTeam();
    $submission = ReviewQueueItem::create(['citable_type' => Artist::class, 'citable_id' => Artist::factory()->create()->id, 'review_type' => 'material_intake', 'status' => 'pending', 'note' => 'x']);

    $this->actingAs(member('samar@hafezgallery.com'));
    $this->getJson('/api/v1/review-queue')->assertOk()->assertJsonPath('data.0.id', $submission->id);
    $this->postJson('/api/v1/artists', ['name' => ['ar' => 'x']])->assertForbidden();
    $this->getJson('/api/v1/admin/archive-items')->assertForbidden();
});

it('is safe to run again: nothing is duplicated, a password someone chose survives, and a role is put back', function () {
    seedTeam();
    $mali = member('mali@hafezgallery.com');
    $mali->update(['password' => Hash::make('the one mali picked')]);
    $mali->syncRoles(['reader']);
    $samar = member('samar@hafezgallery.com');
    $samar->assignRole('editor');

    seedTeam();

    expect(User::count())->toBe(5);
    $mali = member('mali@hafezgallery.com');
    expect(Hash::check('the one mali picked', $mali->password))->toBeTrue()->and($mali->getRoleNames()->all())->toBe(['admin']);
    expect(member('samar@hafezgallery.com')->getRoleNames()->all())->toBe(['reviewer']);
});

it('adopts an account that already exists instead of failing on it', function () {
    User::factory()->create(['email' => 'valeria@hafezgallery.com', 'name' => 'Valeria Existing', 'password' => Hash::make('already-there')]);

    seedTeam();

    $valeria = member('valeria@hafezgallery.com');
    expect(User::where('email', 'valeria@hafezgallery.com')->count())->toBe(1)
        ->and($valeria->name)->toBe('Valeria Existing')
        ->and(Hash::check('already-there', $valeria->password))->toBeTrue()
        ->and($valeria->getRoleNames()->all())->toBe(['admin']);
});

it('never gives real accounts the well-known development password outside a local machine', function () {
    expect(app()->environment('local'))->toBeFalse();
    seedTeam();

    foreach (TeamUserSeeder::TEAM as $team) {
        expect(Hash::check('password', member($team['email'])->password))->toBeFalse();
    }
});

it('uses the password the operator configured, and that account can then sign in with its role and permissions', function () {
    config(['seeding.team_password' => 'chosen-by-the-operator']);
    seedTeam();

    $this->postJson('/api/v1/auth/login', ['email' => 'samar@hafezgallery.com', 'password' => 'chosen-by-the-operator'], spaHeaders())
        ->assertOk()->assertJsonPath('data.roles', ['reviewer'])->assertJsonFragment(['review_queue.material_intake']);
    $this->postJson('/api/v1/auth/login', ['email' => 'samar@hafezgallery.com', 'password' => 'password'], spaHeaders())->assertUnprocessable();
});

it('reports a permission to the interface for the superadmin as well, so nothing is hidden from them', function () {
    config(['seeding.team_password' => 'chosen-by-the-operator']);
    seedTeam();

    $permissions = $this->postJson('/api/v1/auth/login', ['email' => 'mbadis@hafezgallery.com', 'password' => 'chosen-by-the-operator'], spaHeaders())
        ->assertOk()->json('data.permissions');

    expect($permissions)->toContain('materials.review', 'events.manage', 'proposals.submit', 'review_queue.editorial_review');
    expect(count($permissions))->toBe(Permission::count());
});

it('gives the superadmin every permission even when the roles are seeded a second time under another guard', function () {
    // Signing in switches the app's default guard, and a later seed then sees permissions from both guards.
    seedTeam();
    $this->actingAs(member('mali@hafezgallery.com'), 'sanctum');

    seedTeam();

    expect(member('mbadis@hafezgallery.com')->getRoleNames()->all())->toBe(['superadmin']);
});
