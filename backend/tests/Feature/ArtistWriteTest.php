<?php

use App\Models\Artist;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('creates an artist mapping the nested payload to columns', function () {
    $editor = editorUser();

    $response = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'edit_summary' => 'Initial import',
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.name.ar', 'عبدالحليم رضوي')
        ->assertJsonPath('data.name.en', 'Abdulhalim Radwi')
        ->assertJsonPath('data.birth.display', 'c. 1939')
        ->assertJsonPath('data.birth.year_from', 1939)
        ->assertJsonPath('data.birth.year_to', 1941)
        ->assertJsonPath('data.birth.calendar', 'gregorian')
        ->assertJsonPath('data.birth.certainty', 'circa')
        ->assertJsonPath('data.birth.place.ar', 'مكة')
        ->assertJsonPath('data.legacy_code', 'AR999')
        ->assertJsonPath('data.publication_status', 'published')
        ->assertJsonPath('data.slug', 'abdulhalim-radwi');

    $this->assertDatabaseHas('artists', [
        'id' => $response->json('data.id'),
        'name_ar' => 'عبدالحليم رضوي',
        'bio_en' => 'Artist biography',
        'birth_place_en' => 'Makkah',
        'living_status' => 'deceased',
    ]);
});

it('blocks anonymous and reader users from writing', function (string $method, string $uri) {
    $artist = Artist::factory()->published()->create();
    $uri = str_replace('{artist}', (string) $artist->id, $uri);

    // Anonymous
    $this->json($method, $uri, [])->assertUnauthorized();

    // Reader
    $this->actingAs(makeUser('reader'))->json($method, $uri, [])->assertForbidden();
})->with([
    'create' => ['POST', '/api/v1/artists'],
    'update' => ['PATCH', '/api/v1/artists/{artist}'],
    'delete' => ['DELETE', '/api/v1/artists/{artist}'],
    'restore' => ['POST', '/api/v1/artists/{artist}/restore'],
    'add variant' => ['POST', '/api/v1/artists/{artist}/variants'],
    'verify' => ['POST', '/api/v1/artists/{artist}/verify'],
]);

it('updates an artist partially and never touches the slug', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create(['name_en' => 'Keep My Slug']);

    $response = $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", [
        'bio' => ['en' => 'Updated bio'],
        'slug' => 'hacked-slug',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.bio.en', 'Updated bio')
        ->assertJsonPath('data.slug', 'keep-my-slug');
});

it('soft deletes and restores', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->deleteJson("/api/v1/artists/{$artist->id}")->assertNoContent();

    expect($artist->refresh()->trashed())->toBeTrue();
    $this->assertDatabaseHas('artists', ['id' => $artist->id, 'deleted_at' => $artist->deleted_at]);

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $artist->id);

    expect($artist->refresh()->trashed())->toBeFalse();
});

it('never binds slugs into numeric artist routes', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    // A slug must not resolve against the numeric-bound PATCH route; the
    // only URI match is the public GET-by-slug route, hence 405.
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->slug}", ['bio' => ['en' => 'x']])
        ->assertStatus(405);

    // The two-segment verify URI does not match the GET-by-slug regex pattern,
    // so no route matches at all and the request 404s.
    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->slug}/verify", ['status' => 'verified'])
        ->assertStatus(404);
});

it('writes search columns on create', function () {
    $artist = Artist::factory()->published()->create([
        'name_ar' => 'أحمد المغلوث',
        'name_en' => 'Ahmad Almaghlout',
        'legacy_code' => 'AR036',
    ]);

    expect($artist->search_text)->toContain('احمد المغلوث')
        ->and($artist->search_text)->toContain('ahmad almaghlout')
        ->and($artist->search_compact)->toBe('احمدالمغلوثahmadalmaghloutar036');
});

it('keeps the check constraint satisfied at the database level', function () {
    expect(fn () => DB::table('artists')->insert([
        'name_ar' => null,
        'name_en' => null,
        'slug' => 'no-names',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
