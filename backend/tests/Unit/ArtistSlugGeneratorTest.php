<?php

use App\Models\Artist;
use App\Support\ArtistSlugGenerator;

it('generates a slug from the english name', function () {
    $artist = Artist::factory()->englishOnly()->make(['name_en' => 'Abdulhalim Radwi']);

    expect(ArtistSlugGenerator::generate($artist))->toBe('abdulhalim-radwi');
});

it('falls back to a random slug without an english name', function () {
    $artist = Artist::factory()->arabicOnly()->make();

    $slug = ArtistSlugGenerator::generate($artist);

    expect($slug)->toMatch('/^artist-[a-z0-9]{8}$/');
});

it('appends incrementing suffixes on collisions including soft deleted', function () {
    $first = Artist::factory()->create(['name_en' => 'Same Name']);
    expect($first->slug)->toBe('same-name');

    $second = Artist::factory()->create(['name_en' => 'Same Name']);
    expect($second->slug)->toBe('same-name-2');

    $third = Artist::factory()->create(['name_en' => 'Same Name']);
    expect($third->slug)->toBe('same-name-3');

    $second->delete();

    $fourth = Artist::factory()->create(['name_en' => 'Same Name']);
    expect($fourth->slug)->toBe('same-name-4');
});

it('keeps the slug on rename', function () {
    $artist = Artist::factory()->create(['name_en' => 'Original Name']);

    $artist->update(['name_en' => 'Completely Different']);

    expect($artist->slug)->toBe('original-name');
});
