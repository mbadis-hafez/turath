<?php

use App\Models\Artist;
use Illuminate\Support\Facades\DB;

it('rebuilds the parent search columns when variants change', function () {
    $artist = Artist::factory()->englishOnly()->published()->create([
        'name_en' => 'Searchable Name',
    ]);

    expect($artist->search_text)->toBe('searchable name');

    $variant = $artist->variants()->create(['name' => 'Extra Alias', 'type' => 'alias']);

    expect($artist->refresh()->search_text)->toContain('extra alias');

    $variant->delete();

    expect($artist->refresh()->search_text)->not->toContain('extra alias');
});

it('rebuilds search columns when name or legacy code change', function () {
    $artist = Artist::factory()->englishOnly()->published()->create(['name_en' => 'Old Name']);

    $artist->update(['name_en' => 'New Name', 'legacy_code' => 'AR777']);

    expect($artist->refresh()->search_text)->toBe('new name ar777');
});

it('restores search columns via the artisan command', function () {
    $artist = Artist::factory()->englishOnly()->published()->create(['name_en' => 'Lost Search']);
    $other = Artist::factory()->englishOnly()->published()->create(['name_en' => 'Kept Search']);

    DB::table('artists')->where('id', $artist->id)->update(['search_text' => null, 'search_compact' => null]);

    $this->artisan('artists:rebuild-search')->assertSuccessful();

    $artist->refresh();
    $other->refresh();

    expect($artist->search_text)->toBe('lost search')
        ->and($artist->search_compact)->toBe('lostsearch')
        ->and($other->search_text)->toBe('kept search');
});
