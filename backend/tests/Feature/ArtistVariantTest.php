<?php

use App\Models\Artist;

it('adds, updates and deletes variants', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $response = $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'Abdulhalim Radwi Jr',
        'type' => 'alias',
        'source_note' => 'Gallery records',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Abdulhalim Radwi Jr')
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('data.type', 'alias')
        ->assertJsonPath('data.source_note', 'Gallery records');

    $variantId = $response->json('data.id');

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/variants/{$variantId}", [
        'name' => 'A. Radwi',
        'type' => 'pen_name',
    ])->assertOk()->assertJsonPath('data.name', 'A. Radwi')->assertJsonPath('data.type', 'pen_name');

    $this->actingAs($editor)->deleteJson("/api/v1/artists/{$artist->id}/variants/{$variantId}")
        ->assertNoContent();

    $this->assertDatabaseMissing('artist_name_variants', ['id' => $variantId]);
});

it('detects the variant language from the script', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'عبد الحليم رضوي',
        'type' => 'alias',
    ])->assertCreated()->assertJsonPath('data.language', 'ar');
});

it('rejects duplicate variant names case-insensitively', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'Abdullah Alshaikh',
        'type' => 'transliteration',
    ])->assertCreated();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'abdullah alshaikh',
        'type' => 'alias',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

it('scopes variant routes to the parent artist', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();
    $other = Artist::factory()->published()->create();

    $variant = $artist->variants()->create(['name' => 'Scoped Variant', 'type' => 'alias']);

    $this->actingAs($editor)
        ->patchJson("/api/v1/artists/{$other->id}/variants/{$variant->id}", ['name' => 'Nope'])
        ->assertNotFound();

    $this->actingAs($editor)
        ->deleteJson("/api/v1/artists/{$other->id}/variants/{$variant->id}")
        ->assertNotFound();
});

it('validates variant payloads', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'x',
        'type' => 'nickname',
    ])->assertUnprocessable()->assertJsonValidationErrors(['type']);

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'x',
        'type' => 'alias',
        'language' => 'fr',
    ])->assertUnprocessable()->assertJsonValidationErrors(['language']);
});
