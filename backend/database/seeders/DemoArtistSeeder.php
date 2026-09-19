<?php

namespace Database\Seeders;

use App\Models\Artist;
use Illuminate\Database\Seeder;

class DemoArtistSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach (range(1, 30) as $i) {
            $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $state = match ($i % 4) {
                0 => 'published',
                1 => 'draft',
                2 => ['published', 'verified'],
                default => 'published',
            };

            Artist::updateOrCreate(
                ['name_en' => "Demo Artist {$number}"],
                [
                    'name_ar' => "فنان تجريبي {$number}",
                    'bio_en' => fake('en_US')->paragraphs(2, true),
                    'bio_ar' => fake()->paragraphs(2, true),
                    'publication_status' => is_array($state) ? $state[0] : $state,
                    'verified_status' => is_array($state) ? $state[1] : 'unverified',
                ],
            );
        }

        // A couple of single-script demo artists.
        Artist::updateOrCreate(['name_en' => 'Demo English Only'], [
            'name_ar' => null,
            'publication_status' => 'published',
        ]);

        Artist::updateOrCreate(['name_ar' => 'فنان عربي فقط'], [
            'name_en' => null,
            'publication_status' => 'published',
        ]);
    }
}
