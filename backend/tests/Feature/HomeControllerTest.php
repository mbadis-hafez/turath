<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Source;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;

it('returns a public, database-backed home overview without draft records', function () {
    $artist = Artist::factory()->published()->create(['name_ar' => 'فنان منشور', 'name_en' => 'Published Artist']);
    Artist::factory()->draft()->create(['name_ar' => 'فنان مسودة', 'name_en' => 'Draft Artist']);

    $artwork = Artwork::factory()->published()->create(['artist_id' => $artist->id]);
    Artwork::factory()->draft()->create();

    $archive = ArchiveItem::factory()->published()->create([
        'title_ar' => 'مقال منشور',
        'title_en' => 'Published article',
        'place_ar' => 'الرياض',
        'place_en' => 'Riyadh',
    ]);
    $archive->links()->create(['linkable_type' => Artist::class, 'linkable_id' => $artist->id, 'role' => 'about']);
    ArchiveItem::factory()->draft()->create(['title_ar' => 'مادة مسودة']);

    $theme = Theme::query()->create(['label_ar' => 'المدينة', 'label_en' => 'The city']);
    DB::table('theme_taggables')->insert([
        'theme_id' => $theme->id,
        'taggable_type' => Artwork::class,
        'taggable_id' => $artwork->id,
    ]);
    Source::factory()->create();

    $response = $this->getJson('/api/v1/home');

    $response->assertOk()
        ->assertJsonPath('data.stats.materials', 1)
        ->assertJsonPath('data.stats.artists', 1)
        ->assertJsonPath('data.stats.artworks', 1)
        ->assertJsonPath('data.stats.sources', 1)
        ->assertJsonPath('data.archive_feature.title.en', 'Published article')
        ->assertJsonPath('data.artists.0.slug', $artist->slug)
        ->assertJsonPath('data.places.0.name.en', 'Riyadh')
        ->assertJsonPath('data.themes.0.label.en', 'The city')
        ->assertJsonMissing(['Draft Artist', 'مادة مسودة']);
});

it('shows a portrait url only for a published artist with a clear-rights portrait', function () {
    Artist::factory()->published()->create([
        'name_en' => 'With Portrait', 'portrait_path' => 'portraits/a.jpg', 'portrait_rights_status' => 'licensed',
    ]);
    $noPortrait = Artist::factory()->published()->create(['name_en' => 'No Portrait', 'portrait_path' => null]);

    $data = collect($this->getJson('/api/v1/home')->assertOk()->json('data.artists'))->keyBy('name.en');

    expect($data['With Portrait']['portrait_url'])->toContain('/portrait')
        ->and($data[$noPortrait->name_en]['portrait_url'])->toBeNull();
});

it('shows a thumbnail url only for the archive feature when its original file is a clear image', function () {
    $item = ArchiveItem::factory()->published()->publicAccess()->create(['title_en' => 'Featured item']);
    $file = $item->files()->create([
        'role' => 'original', 'disk' => 'local', 'path' => 'archive-files/a.jpg',
        'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'sha256' => str_repeat('a', 64),
    ]);

    $response = $this->getJson('/api/v1/home')->assertOk();

    expect($response->json('data.archive_feature.thumbnail_url'))
        ->toBe("/api/v1/archive-items/{$item->id}/files/{$file->id}/download");
});

it('omits the thumbnail when the archive feature has no file or the file is not an image', function () {
    $item = ArchiveItem::factory()->published()->publicAccess()->create(['title_en' => 'No file']);

    $response = $this->getJson('/api/v1/home')->assertOk();
    expect($response->json('data.archive_feature.thumbnail_url'))->toBeNull();

    $item->files()->create([
        'role' => 'original', 'disk' => 'local', 'path' => 'archive-files/a.pdf',
        'mime_type' => 'application/pdf', 'size_bytes' => 100, 'sha256' => str_repeat('b', 64),
    ]);

    $response = $this->getJson('/api/v1/home')->assertOk();
    expect($response->json('data.archive_feature.thumbnail_url'))->toBeNull();
});

it('hides the thumbnail for a restricted item, like every other full-access field', function () {
    $item = ArchiveItem::factory()->published()->create(['title_en' => 'Restricted item']);
    $item->files()->create([
        'role' => 'original', 'disk' => 'local', 'path' => 'archive-files/a.jpg',
        'mime_type' => 'image/jpeg', 'size_bytes' => 100, 'sha256' => str_repeat('c', 64),
    ]);

    $response = $this->getJson('/api/v1/home')->assertOk();

    expect($response->json('data.archive_feature.restricted'))->toBeTrue()
        ->and($response->json('data.archive_feature.thumbnail_url'))->toBeNull();
});
