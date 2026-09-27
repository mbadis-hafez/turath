<?php

use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\ReviewQueueItem;
use Spatie\Activitylog\Models\Activity;

// --- US1: creating an artist stages a creation-review item, no direct-write bypass ---------------

it('creating an artist creates it unreviewed, with an open draft creation-review item', function () {
    $editor = editorUser();

    $response = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->assertCreated();
    $id = $response->json('data.id');

    $artist = Artist::find($id);
    expect($artist->creation_approved_at)->toBeNull();

    $proposal = EditProposal::where('citable_type', Artist::class)->where('citable_id', $id)->first();
    expect($proposal)->not->toBeNull()
        ->and($proposal->is_creation)->toBeTrue()
        ->and($proposal->status)->toBe('draft')
        ->and($proposal->proposed_by_user_id)->toBe($editor->id);
});

it('submitting the creation review requires no prior edit and reaches the queue — the reported bug', function () {
    $editor = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');

    // No edit made between create and submit — this is exactly what 422'd
    // with a swallowed generic message before this feature.
    $response = $this->actingAs($editor)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertOk();

    expect($response->json('data.status'))->toBe('pending')
        ->and($response->json('data.is_creation'))->toBeTrue();
    expect(ReviewQueueItem::where('citable_type', Artist::class)->where('citable_id', $id)->where('status', 'pending')->exists())->toBeTrue();
});

it('refuses to submit a creation review to anyone but its own creator', function () {
    $editor = editorUser();
    $another = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');

    $this->actingAs($another)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertForbidden();
});

it('refuses to publish or verify an artist whose creation has not been reviewed, regardless of permissions held', function () {
    $editor = editorUser();
    $superadmin = makeUser('superadmin');
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');

    foreach ([$editor, $superadmin] as $actor) {
        $this->actingAs($actor)->patchJson("/api/v1/artists/{$id}", ['publication_status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors(['completeness.creation_review']);
        $this->actingAs($actor)->postJson("/api/v1/artists/{$id}/verify", ['status' => 'verified'])
            ->assertUnprocessable()->assertJsonValidationErrors(['completeness.creation_review']);
    }
});

// --- US2: reviewer approves or requests changes ------------------------------------------------

it('a reviewer approves a creation review: the artist becomes ordinary, eligible for verify/publish', function () {
    $editor = editorUser();
    $admin = makeUser('admin'); // holds review permissions and artists.manage
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');
    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertOk();

    $proposal = EditProposal::where('citable_type', Artist::class)->where('citable_id', $id)->first();

    $this->actingAs($admin)->postJson("/api/v1/proposals/{$proposal->id}/approve")->assertOk();

    $artist = Artist::find($id)->fresh();
    expect($artist->creation_approved_at)->not->toBeNull()
        ->and(Activity::where('subject_type', Artist::class)->where('subject_id', $id)->where('event', 'creation_approved')->exists())->toBeTrue();

    // Ordinary now: publish/verify no longer blocked by creation review (still subject to completeness).
    $this->actingAs($admin)->postJson("/api/v1/artists/{$id}/verify", ['status' => 'verified'])->assertUnprocessable()
        ->assertJsonMissingValidationErrors(['completeness.creation_review']);
});

it('a reviewer requests changes on a creation review: the editor sees the note and can resubmit', function () {
    $editor = editorUser();
    $admin = makeUser('admin');
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');
    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertOk();
    $proposal = EditProposal::where('citable_type', Artist::class)->where('citable_id', $id)->first();

    $this->actingAs($admin)->postJson("/api/v1/proposals/{$proposal->id}/request-changes", ['review_note' => 'add a biography'])->assertOk();

    expect($proposal->fresh()->status)->toBe('changes_requested');

    $resubmit = $this->actingAs($editor)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertOk();
    expect($resubmit->json('data.status'))->toBe('pending');
});

it('refuses a creator from approving or requesting changes on their own creation review', function () {
    $editor = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');
    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$id}/creation/submit")->assertOk();
    $proposal = EditProposal::where('citable_type', Artist::class)->where('citable_id', $id)->first();

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$proposal->id}/approve")->assertForbidden();
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$proposal->id}/request-changes", ['review_note' => 'x'])->assertForbidden();
});

// --- US3: visibility scoping ---------------------------------------------------------------------

it('excludes an unapproved artist from admin/artists when linkable_only is set, but not from the plain registry listing', function () {
    $editor = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload(['name' => ['ar' => 'فنان غير معتمد', 'en' => 'Unapproved Artist']]))->json('data.id');

    $picker = $this->actingAs($editor)->getJson('/api/v1/admin/artists?linkable_only=1')->assertOk();
    expect(collect($picker->json('data'))->pluck('id'))->not->toContain($id);

    $registry = $this->actingAs($editor)->getJson('/api/v1/admin/artists')->assertOk();
    $row = collect($registry->json('data'))->firstWhere('id', $id);
    expect($row)->not->toBeNull()
        ->and($row['creation_approved_at'])->toBeNull();
});

it('the staff curation view still shows an unapproved artist, with creation_approved_at and creation_review visible', function () {
    $editor = editorUser();
    $id = $this->actingAs($editor)->postJson('/api/v1/artists', artistPayload())->json('data.id');

    $response = $this->actingAs($editor)->getJson("/api/v1/artists/{$id}/curation")->assertOk();
    expect($response->json('data.creation_approved_at'))->toBeNull()
        ->and($response->json('data.creation_review.status'))->toBe('draft')
        ->and($response->json('data.creation_review.created_by_user_id'))->toBe($editor->id);

    expect(Artist::where('id', $id)->exists())->toBeTrue();
});
