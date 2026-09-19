<?php

use App\Models\Artist;
use Database\Seeders\ArtistSeeder;

it('lists published artists only and excludes drafts and trashed rows', function () {
    Artist::factory()->published()->create(['name_en' => 'Published One']);
    Artist::factory()->draft()->create(['name_en' => 'Draft One']);
    Artist::factory()->published()->create(['name_en' => 'Trashed One'])->delete();

    $response = $this->getJson('/api/v1/artists');

    $response->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name.en', 'Published One')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'slug', 'name', 'birth', 'death', 'living_status', 'verified_status'],
            ],
            'links', 'meta',
        ])
        ->assertJsonPath('meta.per_page', 24);
});

it('caps per_page at 100 and rejects invalid values', function () {
    Artist::factory()->published()->count(30)->create();

    $this->getJson('/api/v1/artists?per_page=100')->assertOk()->assertJsonCount(30, 'data');
    $this->getJson('/api/v1/artists?per_page=101')->assertUnprocessable();
});

it('honors status=all only for users with artists.manage', function () {
    Artist::factory()->published()->create(['name_en' => 'Published One']);
    Artist::factory()->draft()->create(['name_en' => 'Draft One']);
    Artist::factory()->create(['name_en' => 'Hidden One', 'publication_status' => 'hidden']);

    // Anonymous: status=all ignored, published only.
    $this->getJson('/api/v1/artists?status=all')->assertOk()->assertJsonCount(1, 'data');

    // Reader has no artists.manage either.
    $reader = makeUser('reader');
    $this->actingAs($reader)->getJson('/api/v1/artists?status=all')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs(editorUser())->getJson('/api/v1/artists?status=all')
        ->assertOk()->assertJsonCount(3, 'data');
});

it('sorts stably across pages for every documented sort', function (string $sort) {
    Artist::factory()->published()->count(35)->create();

    $ids = [];

    foreach (range(1, 4) as $page) {
        $response = $this->getJson("/api/v1/artists?sort={$sort}&per_page=10&page={$page}");

        $ids = array_merge($ids, collect($response->json('data'))->pluck('id')->all());
    }

    expect($ids)->toHaveCount(35)
        ->and(array_unique($ids))->toHaveCount(35);
})->with(['name_ar', '-name_ar', 'name_en', '-name_en', 'created_at', '-created_at']);

it('filters by verified and living status', function () {
    Artist::factory()->published()->verified()->create(['living_status' => 'deceased']);
    Artist::factory()->published()->create(['living_status' => 'living']);

    $this->getJson('/api/v1/artists?verified_status=verified')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/artists?living_status=living')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/artists?living_status=bogus')->assertUnprocessable();
});

it('searches across names, variants, typos and legacy codes', function (string $q, string $expectedEn) {
    $this->seed(ArtistSeeder::class);

    $response = $this->getJson('/api/v1/artists?q='.urlencode($q));

    $names = collect($response->json('data'))->pluck('name.en')->filter()->all();

    expect($names)->toContain($expectedEn);
})->with([
    'arabic name without hamza' => ['احمد', 'Ahmad Almaghlout'],
    'arabic two tokens reversed order' => ['الحليم عبد', 'Abdulhalim Radwi'],
    'latin variant spelling' => ['Almaghlouth', 'Ahmad Almaghlout'],
    'typo finds the artist' => ['فؤاذ مفربل', 'Fouad Mougharbel'],
    'legacy code' => ['AR013', 'Abdulhalim Radwi'],
]);

it('never exposes typo variants in also_known_as but still finds them', function () {
    $this->seed(ArtistSeeder::class);

    // Detail: typo absent from also_known_as.
    $slug = Artist::where('legacy_code', 'AR060')->first()->slug;

    $detail = $this->getJson("/api/v1/artists/{$slug}")->assertOk();

    $aka = collect($detail->json('data.also_known_as'))->pluck('name')->all();

    expect($aka)->not->toContain('فؤاذ مفربل');

    // But the typo is searchable.
    $list = $this->getJson('/api/v1/artists?q='.urlencode('فؤاذ مفربل'))->assertOk();

    expect(collect($list->json('data'))->pluck('name.en')->filter()->all())->toContain('Fouad Mougharbel');

    // And it never leaks into any list rendering either (also_known_as only
    // appears on the detail, but assert the typo text is absent from the
    // whole list response body).
    $fullList = $this->getJson('/api/v1/artists?per_page=100')->assertOk();

    expect($fullList->getContent())->not->toContain('فؤاذ مفربل');
});

it('matches all tokens, so non-matching tokens narrow results away', function () {
    $this->seed(ArtistSeeder::class);

    // "احمد" alone matches, adding "جاها" (another artist) matches nothing.
    $this->getJson('/api/v1/artists?q='.urlencode('احمد'))->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/artists?q='.urlencode('احمد جاها'))->assertJsonCount(0, 'data');
});

it('treats a punctuation-only query as empty', function () {
    Artist::factory()->published()->create();

    $this->getJson('/api/v1/artists?q='.urlencode('%_'))->assertOk()->assertJsonCount(1, 'data');
});
