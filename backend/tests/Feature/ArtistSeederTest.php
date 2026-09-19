<?php

use App\Models\Artist;
use App\Models\ArtistNameVariant;
use App\Models\User;
use Database\Seeders\ArtistSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoArtistSeeder;

it('seeds the exact fixture artists with their variants', function () {
    $this->seed(ArtistSeeder::class);

    expect(Artist::count())->toBe(19)
        ->and(ArtistNameVariant::count())->toBe(18)
        ->and(Artist::where('legacy_code', 'AR013')->first()->name_ar)->toBe('عبدالحليم رضوي')
        ->and(Artist::where('legacy_code', 'AR013')->first()->variants()->pluck('name')->all())
        ->toBe(['عبد الحليم رضوي'])
        ->and(Artist::whereNull('legacy_code')->first()->name_en)->toBe('Safeya Binzagr')
        ->and(Artist::where('legacy_code', 'AR026')->first()->variants()->count())->toBe(2)
        ->and(Artist::where('legacy_code', 'AR001')->first()->publication_status)->toBe('published')
        ->and(Artist::where('legacy_code', 'AR001')->first()->verified_status)->toBe('unverified');
});

it('detects variant language from the script', function () {
    $this->seed(ArtistSeeder::class);

    $arabic = ArtistNameVariant::where('name', 'عبد الحليم رضوي')->first();
    $latin = ArtistNameVariant::where('name', 'Abdullah Alshaikh')->first();

    expect($arabic->language)->toBe('ar')
        ->and($latin->language)->toBe('en');
});

it('is idempotent across reruns', function () {
    $this->seed(ArtistSeeder::class);
    $this->seed(ArtistSeeder::class);
    $this->seed(ArtistSeeder::class);

    expect(Artist::count())->toBe(19)
        ->and(ArtistNameVariant::count())->toBe(18);
});

it('does not seed demo artists outside the local environment', function () {
    $this->seed(DemoArtistSeeder::class);

    expect(Artist::count())->toBe(0);
});

it('database seeder runs artist seeders in testing without demo artists or local users', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Artist::count())->toBe(19)
        ->and(User::count())->toBe(0);
});
