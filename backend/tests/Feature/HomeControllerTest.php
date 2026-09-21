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
