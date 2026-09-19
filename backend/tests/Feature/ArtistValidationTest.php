<?php

use App\Models\Artist;

it('rejects names in the wrong script', function () {
    $editor = editorUser();

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'name' => ['ar' => 'Abdulhalim Radwi', 'en' => 'Abdulhalim Radwi'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['name.ar']);

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'name' => ['ar' => 'عبدالحليم رضوي', 'en' => 'عبدالحليم رضوي'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['name.en']);
});

it('requires at least one of the two names', function () {
    $editor = editorUser();

    $this->actingAs($editor)->postJson('/api/v1/artists', [
        'name' => ['ar' => null, 'en' => null],
    ])->assertUnprocessable()->assertJsonValidationErrors(['name.ar', 'name.en']);
});

it('enforces calendar year ranges', function () {
    $editor = editorUser();

    // Hijri out of range.
    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'birth' => ['year_from' => 1800, 'year_to' => 1800, 'calendar' => 'hijri', 'certainty' => 'exact'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['birth.year_from']);

    // Gregorian before 1700.
    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'birth' => ['year_from' => 1600, 'year_to' => 1600, 'calendar' => 'gregorian', 'certainty' => 'exact'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['birth.year_from']);

    // Gregorian current year + 1.
    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'birth' => ['year_from' => (int) date('Y') + 1, 'year_to' => (int) date('Y') + 1, 'calendar' => 'gregorian', 'certainty' => 'exact'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['birth.year_from']);

    // In-range hijri passes.
    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'legacy_code' => 'AR500',
        'birth' => ['year_from' => 1350, 'year_to' => 1350, 'calendar' => 'hijri', 'certainty' => 'exact'],
    ]))->assertCreated();
});

it('rejects inverted year ranges', function () {
    $editor = editorUser();

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'birth' => ['year_from' => 1940, 'year_to' => 1939, 'calendar' => 'gregorian', 'certainty' => 'range'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['birth.year_to']);
});

it('rejects exact certainty with differing years', function () {
    $editor = editorUser();

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'birth' => ['year_from' => 1939, 'year_to' => 1940, 'calendar' => 'gregorian', 'certainty' => 'exact'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['birth.certainty']);
});

it('validates the legacy code format and uniqueness', function () {
    $editor = editorUser();
    Artist::factory()->create(['legacy_code' => 'AR001']);

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload(['legacy_code' => 'ar001']))
        ->assertUnprocessable()->assertJsonValidationErrors(['legacy_code']);

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload(['legacy_code' => 'AR001']))
        ->assertUnprocessable()->assertJsonValidationErrors(['legacy_code']);

    // Same code on update is fine.
    $artist = Artist::where('legacy_code', 'AR001')->first();
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['legacy_code' => 'AR001'])
        ->assertOk();
});

it('validates enum fields', function () {
    $editor = editorUser();

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload(['living_status' => 'maybe']))
        ->assertUnprocessable()->assertJsonValidationErrors(['living_status']);

    $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload(['publication_status' => 'archived']))
        ->assertUnprocessable()->assertJsonValidationErrors(['publication_status']);
});
