<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The Bidayaat team's accounts. Safe to run again: existing accounts keep their password and are only
 * brought back to the role listed here.
 */
class TeamUserSeeder extends Seeder
{
    /** @var array<int, array{email: string, name: string, role: string}> */
    public const TEAM = [
        ['email' => 'mali@hafezgallery.com', 'name' => 'Mali', 'role' => 'admin'],
        ['email' => 'mbadis@hafezgallery.com', 'name' => 'Mbadis', 'role' => 'superadmin'],
        ['email' => 'fidha.fatma@hafezgallery.com', 'name' => 'Fidha Fatma', 'role' => 'editor'],
        ['email' => 'valeria@hafezgallery.com', 'name' => 'Valeria', 'role' => 'admin'],
        ['email' => 'samar@hafezgallery.com', 'name' => 'Samar', 'role' => 'reviewer'],
    ];

    public function run(): void
    {
        // The roles must exist first, whichever way this seeder is invoked.
        $this->call(RolesAndPermissionsSeeder::class);

        $configured = config('seeding.team_password');
        $created = [];

        foreach (self::TEAM as $member) {
            $user = User::firstOrNew(['email' => $member['email']]);
            $isNew = ! $user->exists;

            if ($isNew) {
                $password = $this->passwordForNewAccount($configured);
                $user->name = $member['name'];
                $user->password = Hash::make($password);
                $created[] = [$member['email'], $member['role'], $configured !== null || app()->environment('local') ? '(as configured)' : $password];
            }

            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$member['role']]);
        }

        if ($created !== []) {
            $this->command?->info(count($created).' team account(s) created.');
            if ($configured === null && ! app()->environment('local')) {
                $this->command?->warn('No SEED_TEAM_PASSWORD is set, so a random password was generated for each account. It is shown only now; save it.');
                $this->command?->table(['Email', 'Role', 'Password'], $created);
            }
        }
    }

    /** A known password only where the operator chose one or the machine is a local one. */
    private function passwordForNewAccount(?string $configured): string
    {
        if ($configured !== null && $configured !== '') {
            return $configured;
        }

        return app()->environment('local') ? 'password' : Str::password(20);
    }
}
