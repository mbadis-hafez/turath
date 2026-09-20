<?php

use App\Models\Artist;
use App\Models\Artwork;

it('lists only published, non-deleted artworks by default', function () {
    Artwork::factory()->published()->count(2)->create();
    Artwork::factory()->draft()->create();
    Artwork::factory()->published()->create()->delete();

    $response = $this->getJson('/api/v1/artworks');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('lets a manager opt into all statuses via status=all', function () {
    Artwork::factory()->published()->create();
    Artwork::factory()->draft()->create();

    $this->getJson('/api/v1/artworks?status=all')->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs(editorUser())->getJson('/api/v1/artworks?status=all')->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by category, artist, holder and attribution certainty', function () {
    $matisse = Artist::factory()->published()->create();
    $other = Artist::factory()->published()->create();

    $painting = Artwork::factory()->published()->create(['artist_id' => $matisse->id, 'category' => 'painting']);
    Artwork::factory()->published()->create(['artist_id' => $other->id, 'category' => 'sculpture']);
    Artwork::factory()->published()->unattributed()->create(['category' => 'painting']);

    $this->getJson("/api/v1/artworks?artist_id={$matisse->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $painting->id);

    $this->getJson('/api/v1/artworks?category=sculpture')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/artworks?attribution_certainty=unattributed')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('finds an artwork by its artist\'s name', function () {
    $artist = Artist::factory()->published()->create(['name_ar' => 'أحمد المغلوث', 'name_en' => 'Ahmad Almaghlout']);
    Artwork::factory()->published()->create(['artist_id' => $artist->id, 'title_en' => 'Some Painting']);
    Artwork::factory()->published()->create(['title_en' => 'Unrelated']);

    $this->getJson('/api/v1/artworks?q=Almaghlout')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('filters by a creation year range that overlaps the artwork\'s year span', function () {
    Artwork::factory()->published()->create(['creation_year_from' => 1970, 'creation_year_to' => 1970]);
    Artwork::factory()->published()->create(['creation_year_from' => 1990, 'creation_year_to' => 1990]);

    $this->getJson('/api/v1/artworks?year_from=1965&year_to=1975')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('paginates with a default and capped per_page', function () {
    Artwork::factory()->published()->count(3)->create();

    $response = $this->getJson('/api/v1/artworks?per_page=2');

    $response->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});
