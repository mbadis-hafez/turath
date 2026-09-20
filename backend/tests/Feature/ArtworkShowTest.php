<?php

use App\Models\Artwork;

it('shows a published artwork to anyone', function () {
    $artwork = Artwork::factory()->published()->create();

    $this->getJson("/api/v1/artworks/{$artwork->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $artwork->id);
});

it('404s a draft artwork for anonymous users and editors can see it', function () {
    $artwork = Artwork::factory()->draft()->create();

    $this->getJson("/api/v1/artworks/{$artwork->id}")->assertNotFound();

    $this->actingAs(editorUser())->getJson("/api/v1/artworks/{$artwork->id}")
        ->assertOk()->assertJsonPath('data.id', $artwork->id);
});

it('404s a soft-deleted artwork for anonymous users', function () {
    $artwork = Artwork::factory()->published()->create();
    $artwork->delete();

    $this->getJson("/api/v1/artworks/{$artwork->id}")->assertNotFound();
    $this->actingAs(editorUser())->getJson("/api/v1/artworks/{$artwork->id}")->assertOk();
});

it('renders resolved dimensions and never hides the raw string, even when unparseable', function () {
    $artwork = Artwork::factory()->published()->create([
        'height_cm' => null, 'width_cm' => null, 'depth_cm' => null,
        'dimensions_raw' => 'Not available - tbc',
    ]);

    $this->getJson("/api/v1/artworks/{$artwork->id}")
        ->assertOk()
        ->assertJsonPath('data.dimensions.height_cm', null)
        ->assertJsonPath('data.dimensions.raw', 'Not available - tbc');
});

it('shows an attribution certainty badge-worthy value for non-confirmed works', function () {
    $artwork = Artwork::factory()->published()->unattributed()->create();

    $this->getJson("/api/v1/artworks/{$artwork->id}")
        ->assertOk()
        ->assertJsonPath('data.attribution_certainty', 'unattributed')
        ->assertJsonPath('data.artist', null);
});
