<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@bidayaat.test'],
            ['name' => 'Bidayaat Admin', 'password' => Hash::make('password')],
        );
        $admin->email_verified_at ??= now();
        $admin->save();
        $admin->assignRole('admin');

        $editor = User::firstOrCreate(
            ['email' => 'editor@bidayaat.test'],
            ['name' => 'Bidayaat Editor', 'password' => Hash::make('password')],
        );
        $editor->email_verified_at ??= now();
        $editor->save();
        $editor->assignRole('editor');
    }
}
