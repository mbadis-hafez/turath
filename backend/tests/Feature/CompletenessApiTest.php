<?php

use App\Models\Artist;
use App\Models\FieldCitation;
use App\Models\ReviewQueueItem;
use App\Models\Source;
use App\Models\SourceConflict;
use App\Support\Completeness\ConflictDetector;
use Spatie\Activitylog\Models\Activity;

function citeArtist(Artist $artist, string $fieldKey, mixed $value): FieldCitation
{
    return FieldCitation::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'field_key' => $fieldKey,
        'source_id' => Source::factory()->create()->id,
        'claimed_value' => $value,
    ]);
}

it('creates exactly one open conflict for disagreeing citations, and auto-resolves when they converge', function () {
    $artist = Artist::factory()->create();
    $detector = new ConflictDetector;

    $a = citeArtist($artist, 'birth_year', ['year' => 1939]);
    $detector->check(Artist::class, $artist->id, 'birth_year');
    expect(SourceConflict::count())->toBe(0);

    $b = citeArtist($artist, 'birth_year', ['year' => 1941]);
    $detector->check(Artist::class, $artist->id, 'birth_year');
    $c = citeArtist($artist, 'birth_year', ['year' => 1941]);
    $detector->check(Artist::class, $artist->id, 'birth_year');

    expect(SourceConflict::where('status', 'open')->count())->toBe(1);

    FieldCitation::whereIn('id', [$b->id, $c->id])->delete();
    $detector->check(Artist::class, $artist->id, 'birth_year');

    expect(SourceConflict::where('status', 'open')->count())->toBe(0)
        ->and(SourceConflict::where('status', 'resolved')->count())->toBe(1);
});

it('resolves a conflict, marks the chosen citation primary, and audits it', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $one = citeArtist($artist, 'birth_year', ['year' => 1939]);
    $two = citeArtist($artist, 'birth_year', ['year' => 1941]);
    (new ConflictDetector)->check(Artist::class, $artist->id, 'birth_year');
    $conflict = SourceConflict::first();

    $this->actingAs($editor)->postJson("/api/v1/source-conflicts/{$conflict->id}/resolve", [
        'resolved_source_id' => $one->source_id,
        'resolution_note' => 'Chose the Riyadh archive',
    ])->assertOk()->assertJsonPath('data.status', 'resolved');

    expect($one->refresh()->is_primary)->toBeTrue()
        ->and($two->refresh()->is_primary)->toBeFalse()
        ->and(Activity::where('subject_type', SourceConflict::class)->where('event', 'updated')->exists())->toBeTrue();
});

it('forbids resolving conflicts without the permission', function () {
    $conflict = SourceConflict::factory()->create();

    $this->actingAs(makeUser('reader'))
        ->postJson("/api/v1/source-conflicts/{$conflict->id}/resolve", ['resolved_source_id' => Source::factory()->create()->id])
        ->assertForbidden();
});

it('rejects publishing an artist with blocking gaps and names the missing fields', function () {
    $editor = editorUser();
    $artist = Artist::factory()->draft()->create(['living_status' => 'deceased', 'death_year_from' => null]);

    $response = $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", [
        'publication_status' => 'published',
    ])->assertStatus(422);

    expect(array_keys($response->json('errors')))->toContain('completeness.death_year_or_living_confirmed');
});

it('scopes the dashboard to the current user unless dashboard.manage', function () {
    $owner = editorUser();
    $other = makeUser('contributor');

    $this->actingAs($owner);
    Artist::factory()->create();
    auth()->forgetGuards();

    $this->actingAs($other)->getJson('/api/v1/dashboard/records')
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($other)->getJson("/api/v1/dashboard/records?user_id={$owner->id}")
        ->assertForbidden();

    $this->actingAs($owner)->getJson('/api/v1/dashboard/records')
        ->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($owner)->getJson("/api/v1/dashboard/completeness?user_id={$other->id}")
        ->assertOk();
});

it('exports the gaps report as xlsx', function () {
    $editor = editorUser();

    $this->actingAs($editor)->get('/api/v1/dashboard/export')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('only lists review queue items matching the user permissions', function () {
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/review-queue')
        ->assertOk()->assertJsonCount(0, 'data');
});

it('returns the record title and submission time with each review queue item', function () {
    $artist = Artist::factory()->create(['name_ar' => 'طه الصبان', 'name_en' => 'Taha Al-Sabban']);
    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class, 'citable_id' => $artist->id, 'review_type' => 'data_audit',
        'submitted_at' => now()->subDays(6),
    ]);

    $this->actingAs(editorUser())->getJson('/api/v1/review-queue')
        ->assertOk()
        ->assertJsonPath('data.0.title.en', 'Taha Al-Sabban')
        ->assertJsonPath('data.0.title.ar', 'طه الصبان')
        ->assertJsonPath('data.0.citable_type', 'artists');
});
