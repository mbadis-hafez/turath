<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

function apiHeaders(): array
{
    return ['Origin' => 'http://localhost'];
}

function seedAuthUser(string $email, string $role): User
{
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
}

it('logs in and returns the user resource with roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAuthUser('admin@bidayaat.test', 'admin');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@bidayaat.test',
        'password' => 'password',
    ], apiHeaders());

    $response->assertOk()
        ->assertJsonPath('data.email', 'admin@bidayaat.test')
        ->assertJsonPath('data.roles.0', 'admin')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'roles', 'permissions']]);
});

it('returns 422 for wrong credentials', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAuthUser('editor@bidayaat.test', 'editor');

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'editor@bidayaat.test',
        'password' => 'wrong-password',
    ], apiHeaders());

    $response->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['email']]);
});

it('returns 401 from auth user when logged out and 200 when logged in', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAuthUser('editor@bidayaat.test', 'editor');

    $this->getJson('/api/v1/auth/user', apiHeaders())->assertUnauthorized();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'editor@bidayaat.test',
        'password' => 'password',
    ], apiHeaders())->assertOk();

    $this->getJson('/api/v1/auth/user', apiHeaders())
        ->assertOk()
        ->assertJsonPath('data.email', 'editor@bidayaat.test');
});

it('logs out and invalidates the session', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAuthUser('editor@bidayaat.test', 'editor');

    $this->postJson('/api/v1/auth/login', [
        'email' => 'editor@bidayaat.test',
        'password' => 'password',
    ], apiHeaders())->assertOk();

    $this->postJson('/api/v1/auth/logout', [], apiHeaders())->assertNoContent();

    Auth::forgetGuards();

    $this->getJson('/api/v1/auth/user', apiHeaders())->assertUnauthorized();
});

it('rate limits login after 5 failed attempts per email and ip', function () {
    $email = 'throttle-'.uniqid().'@bidayaat.test';
    $this->seed(RolesAndPermissionsSeeder::class);
    seedAuthUser($email, 'reader');

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ], apiHeaders())->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'wrong-password',
    ], apiHeaders())->assertTooManyRequests();
});
