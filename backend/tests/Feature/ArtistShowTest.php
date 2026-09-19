<?php

use App\Models\Artist;

it('returns 200 for a published artist and hides verifier from guests', function () {
    $artist = Artist::factory()->published()->create([
        'name_en' => 'Public Artist',
        'verified_by_user_id' => editorUser()->id,
        'verified_at' => now(),
        'birth' => [
            'display' => 'c. 1939',
            'year_from' => 1939,
            'year_to' => 1941,
            'calendar' => 'gregorian',
            'certainty' => 'circa',
        ],
        'birth_place_ar' => 'مكة',
        'birth_place_en' => 'Makkah',
    ]);

    $response = $this->getJson("/api/v1/artists/{$artist->slug}");

    $response->assertOk()
        ->assertJsonPath('data.slug', $artist->slug)
        ->assertJsonPath('data.name.en', 'Public Artist')
        ->assertJsonStructure([
            'data' => [
                'id', 'slug', 'name', 'legacy_code', 'bio',
                'birth' => ['display', 'year_from', 'year_to', 'calendar', 'certainty', 'place'],
                'death',
                'also_known_as',
                'verified_at', 'publication_status', 'created_at', 'updated_at',
            ],
        ]);

    expect($response->json('data'))->not->toHaveKey('verified_by');
});

it('shows verified_by to users with artists.manage', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create([
        'verified_by_user_id' => $editor->id,
        'verified_at' => now(),
    ]);

    $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->slug}")
        ->assertOk()
        ->assertJsonPath('data.verified_by.id', $editor->id)
        ->assertJsonPath('data.verified_by.name', $editor->name);
});

it('returns 404 for draft, hidden and trashed artists to anonymous users', function (string $state) {
    $artist = Artist::factory()->published()->create(['name_en' => 'Hidden Artist']);

    match ($state) {
        'draft' => $artist->update(['publication_status' => 'draft']),
        'hidden' => $artist->update(['publication_status' => 'hidden']),
        'trashed' => $artist->delete(),
    };

    $this->getJson("/api/v1/artists/{$artist->slug}")->assertNotFound();
})->with(['draft', 'hidden', 'trashed']);

it('returns 200 for draft and trashed artists to managers', function (string $state) {
    $artist = Artist::factory()->published()->create(['name_en' => 'Managed Artist']);

    match ($state) {
        'draft' => $artist->update(['publication_status' => 'draft']),
        'hidden' => $artist->update(['publication_status' => 'hidden']),
        'trashed' => $artist->delete(),
    };

    $this->actingAs(editorUser())->getJson("/api/v1/artists/{$artist->slug}")->assertOk();
})->with(['draft', 'hidden', 'trashed']);

it('returns 404 for an unknown slug', function () {
    $this->getJson('/api/v1/artists/no-such-artist')->assertNotFound();
});
