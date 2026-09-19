<?php

use App\Models\Artist;

it('audits artist create and update with translated labels and edit summary', function () {
    $editor = editorUser();

    $response = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload([
        'edit_summary' => 'Imported from legacy sheet',
    ]));

    $artistId = $response->json('data.id');

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artistId}", [
        'bio' => ['en' => 'New bio'],
        'edit_summary' => 'Fixed the bio',
    ])->assertOk();

    // Global feed: the update entry carries old/new + bilingual labels.
    $feed = $this->actingAs($editor)->getJson('/api/v1/activity?event=updated&per_page=100')->assertOk();

    $entry = collect($feed->json('data'))->firstWhere('subject_id', $artistId);

    expect($entry)->not->toBeNull()
        ->and($entry['subject_type'])->toBe('Artist')
        ->and($entry['subject_label'])->toBe('Abdulhalim Radwi / عبدالحليم رضوي')
        ->and($entry['edit_summary'])->toBe('Fixed the bio')
        ->and($entry['causer']['id'])->toBe($editor->id);

    $bioChange = collect($entry['changes'])->firstWhere('field', 'bio_en');

    expect($bioChange['label'])->toBe(['ar' => 'السيرة (إنجليزي)', 'en' => 'Biography (English)'])
        ->and($bioChange['old'])->toBe('Artist biography')
        ->and($bioChange['new'])->toBe('New bio');

    // Search columns never appear in the diff.
    $fields = collect($entry['changes'])->pluck('field')->all();

    expect($fields)->not->toContain('search_text')->not->toContain('search_compact');
});

it('returns the artist and its variants history from the per entity endpoint', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create(['name_en' => 'History Artist']);

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", [
        'bio' => ['en' => 'v2'],
    ])->assertOk();

    $variantResponse = $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'History Alias',
        'type' => 'alias',
    ])->assertCreated();

    $variantId = $variantResponse->json('data.id');

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}/variants/{$variantId}", [
        'name' => 'History Alias Updated',
    ])->assertOk();

    $response = $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/activity?per_page=100")->assertOk();

    $subjects = collect($response->json('data'));

    // Artist create + update + variant create + update = 4 entries.
    expect($subjects)->toHaveCount(4)
        ->and($subjects->pluck('subject_type')->unique()->sort()->values()->all())
        ->toBe(['Artist', 'ArtistNameVariant'])
        ->and($subjects->where('subject_type', 'ArtistNameVariant')->pluck('event')->sort()->values()->all())
        ->toBe(['created', 'updated']);
});

it('keeps the per entity history scoped to the one artist', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();
    $other = Artist::factory()->published()->create();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$other->id}", ['bio' => ['en' => 'other']]);

    $response = $this->actingAs($editor)->getJson("/api/v1/artists/{$artist->id}/activity?per_page=100")->assertOk();

    // The other artist's entry must not leak into this artist's history.
    $leaked = collect($response->json('data'))
        ->first(fn ($entry) => $entry['subject_type'] === 'Artist' && $entry['subject_id'] === $other->id);

    expect($leaked)->toBeNull();
});

it('forbids the activity endpoints without activity.view', function () {
    seedRoles();

    $reader = makeUser('reader');
    $artist = Artist::factory()->published()->create();

    $this->actingAs($reader)->getJson("/api/v1/artists/{$artist->id}/activity")->assertForbidden();
    $this->actingAs($reader)->getJson('/api/v1/activity')->assertForbidden();
});

it('labels variant entries bilingually in the global feed', function () {
    $editor = editorUser();
    $artist = Artist::factory()->published()->create();

    $this->actingAs($editor)->postJson("/api/v1/artists/{$artist->id}/variants", [
        'name' => 'Some Alias',
        'type' => 'alias',
    ])->assertCreated();

    $feed = $this->actingAs($editor)->getJson('/api/v1/activity?subject_type=ArtistNameVariant')->assertOk();

    $entry = $feed->json('data.0');

    $nameChange = collect($entry['changes'])->firstWhere('field', 'name');

    expect($nameChange['label'])->toBe(['ar' => 'الاسم', 'en' => 'Name']);
});
