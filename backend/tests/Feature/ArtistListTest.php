<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Theme;
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
                '*' => ['id', 'slug', 'name', 'birth', 'death', 'living_status', 'verified_status', 'portrait_url'],
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

it('lists the portrait url for a published artist with a clear-rights portrait, and null otherwise', function () {
    $withPortrait = Artist::factory()->published()->create(['portrait_path' => 'portraits/a.jpg', 'portrait_rights_status' => 'licensed']);
    $unclearRights = Artist::factory()->published()->create(['portrait_path' => 'portraits/b.jpg', 'portrait_rights_status' => 'unknown']);
    $noPortrait = Artist::factory()->published()->create(['portrait_path' => null]);

    $data = collect($this->getJson('/api/v1/artists')->assertOk()->json('data'))->keyBy('id');

    expect($data[$withPortrait->id]['portrait_url'])->toBe("/api/v1/artists/{$withPortrait->id}/portrait")
        ->and($data[$unclearRights->id]['portrait_url'])->toBeNull()
        ->and($data[$noPortrait->id]['portrait_url'])->toBeNull();
});

it('includes materials_count only counting published linked archive items', function () {
    $artist = Artist::factory()->published()->create();
    $publishedItem = ArchiveItem::factory()->published()->create();
    $draftItem = ArchiveItem::factory()->draft()->create();

    ArchiveItemLink::factory()->create([
        'archive_item_id' => $publishedItem->id,
        'linkable_type' => Artist::class,
        'linkable_id' => $artist->id,
    ]);
    ArchiveItemLink::factory()->create([
        'archive_item_id' => $draftItem->id,
        'linkable_type' => Artist::class,
        'linkable_id' => $artist->id,
    ]);

    $response = $this->getJson('/api/v1/artists')->assertOk();

    expect($response->json('data.0.materials_count'))->toBe(1);
});

it('sorts by most materials first then name', function () {
    $few = Artist::factory()->published()->create(['name_ar' => 'أحمد']);
    $many = Artist::factory()->published()->create(['name_ar' => 'محمود']);
    $none = Artist::factory()->published()->create(['name_ar' => 'زينب']);

    foreach (range(1, 3) as $i) {
        $item = ArchiveItem::factory()->published()->create();
        ArchiveItemLink::factory()->create([
            'archive_item_id' => $item->id,
            'linkable_type' => Artist::class,
            'linkable_id' => $many->id,
        ]);
    }

    foreach (range(1, 1) as $i) {
        $item = ArchiveItem::factory()->published()->create();
        ArchiveItemLink::factory()->create([
            'archive_item_id' => $item->id,
            'linkable_type' => Artist::class,
            'linkable_id' => $few->id,
        ]);
    }

    $response = $this->getJson('/api/v1/artists?sort=-materials_count')->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$many->id, $few->id, $none->id]);
});

it('filters by city', function () {
    Artist::factory()->published()->create(['birth_place_ar' => 'جدة', 'birth_place_en' => 'Jeddah']);
    Artist::factory()->published()->create(['birth_place_ar' => 'الرياض', 'birth_place_en' => 'Riyadh']);

    $this->getJson('/api/v1/artists?city=جدة')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city.ar', 'جدة');
    $this->getJson('/api/v1/artists?city=Riyadh')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.city.en', 'Riyadh');
});

it('filters by theme_id', function () {
    $artist = Artist::factory()->published()->create();
    $other = Artist::factory()->published()->create();
    $theme = Theme::create(['label_ar' => 'تجريد', 'label_en' => 'Abstraction']);
    $artist->themes()->attach($theme);

    $this->getJson('/api/v1/artists?theme_id='.$theme->id)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $artist->id);
    $this->getJson('/api/v1/artists?theme_id='.($theme->id + 1))->assertUnprocessable();
});

it('filters by item_type through published archive items only', function () {
    $artist = Artist::factory()->published()->create();
    $other = Artist::factory()->published()->create();

    $publishedImage = ArchiveItem::factory()->published()->create(['item_type' => 'image']);
    $draftImage = ArchiveItem::factory()->draft()->create(['item_type' => 'image']);

    ArchiveItemLink::factory()->create([
        'archive_item_id' => $publishedImage->id,
        'linkable_type' => Artist::class,
        'linkable_id' => $artist->id,
    ]);
    ArchiveItemLink::factory()->create([
        'archive_item_id' => $draftImage->id,
        'linkable_type' => Artist::class,
        'linkable_id' => $other->id,
    ]);

    $this->getJson('/api/v1/artists?item_type=image')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $artist->id);
    $this->getJson('/api/v1/artists?item_type=bogus')->assertUnprocessable();
});

it('returns facets with counts and excludes their own filter', function () {
    $cityA = Artist::factory()->published()->create(['birth_place_ar' => 'جدة']);
    $cityB = Artist::factory()->published()->create(['birth_place_ar' => 'الرياض']);
    $theme = Theme::create(['label_ar' => 'تجريد', 'label_en' => 'Abstraction']);
    $cityA->themes()->attach($theme);

    $response = $this->getJson('/api/v1/artists?include_facets=1')->assertOk();
    $facets = $response->json('meta.facets');

    expect($facets['city'])->toHaveCount(2)
        ->and(collect($facets['city'])->pluck('value')->all())->toContain('جدة', 'الرياض')
        ->and($facets['theme_id'])->toHaveCount(1)
        ->and($facets['theme_id'][0]['value'])->toBe($theme->id);

    $filtered = $this->getJson('/api/v1/artists?city=جدة&include_facets=1')->assertOk();

    expect($filtered->json('meta.facets.city'))->toHaveCount(2)
        ->and($filtered->json('meta.facets.theme_id'))->toHaveCount(1);
});

it('returns letters reflecting current filters with alef normalization', function () {
    Artist::factory()->published()->create(['name_ar' => 'أحمد']);
    Artist::factory()->published()->create(['name_ar' => 'إبراهيم']);
    Artist::factory()->published()->create(['name_ar' => 'آمنة']);
    Artist::factory()->published()->create(['name_ar' => 'احمد']);
    Artist::factory()->published()->create(['name_en' => 'Brian']);
    Artist::factory()->published()->create(['name_en' => 'alice']);

    $response = $this->getJson('/api/v1/artists?include_facets=1')->assertOk();
    $letters = $response->json('meta.letters');

    expect($letters['ar'])->toContain('أ')
        ->and($letters['ar'])->not->toContain('إ', 'آ', 'ا')
        ->and($letters['en'])->toContain('A', 'B')
        ->and($letters['en'])->not->toContain('a', 'b');
});

it('returns materials_total over the filtered result', function () {
    $artistA = Artist::factory()->published()->create();
    $artistB = Artist::factory()->published()->create();

    foreach (range(1, 3) as $i) {
        $item = ArchiveItem::factory()->published()->create();
        ArchiveItemLink::factory()->create([
            'archive_item_id' => $item->id,
            'linkable_type' => Artist::class,
            'linkable_id' => $artistA->id,
        ]);
    }

    foreach (range(1, 2) as $i) {
        $item = ArchiveItem::factory()->published()->create();
        ArchiveItemLink::factory()->create([
            'archive_item_id' => $item->id,
            'linkable_type' => Artist::class,
            'linkable_id' => $artistB->id,
        ]);
    }

    $this->getJson('/api/v1/artists?include_facets=1')->assertOk()
        ->assertJsonPath('meta.materials_total', 5);
});

it('excludes draft artists from results, facets, letters and totals', function () {
    $published = Artist::factory()->published()->create(['name_ar' => 'أحمد']);
    Artist::factory()->draft()->create(['name_ar' => 'بدر']);

    $item = ArchiveItem::factory()->published()->create();
    ArchiveItemLink::factory()->create([
        'archive_item_id' => $item->id,
        'linkable_type' => Artist::class,
        'linkable_id' => $published->id,
    ]);

    $response = $this->getJson('/api/v1/artists?include_facets=1')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('meta.letters.ar'))->toContain('أ')
        ->and($response->json('meta.letters.ar'))->not->toContain('ب')
        ->and($response->json('meta.materials_total'))->toBe(1);
});
