<?php

namespace Database\Seeders;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $this->call(TeamUserSeeder::class);
    }
}
