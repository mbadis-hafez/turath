<?php

use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\ReviewQueueItem;

it('surfaces pending review-queue items scoped to the reviewer\'s permissions', function () {
    $reviewer = reviewerUser();

    $artist = Artist::factory()->complete()->create();
    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'review_type' => 'archivist_review',
        'status' => 'pending',
        'submitted_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer')->assertOk();

    $response->assertJsonPath('data.summary.waiting_review', 1)
        ->assertJsonCount(1, 'data.needs_review')
        ->assertJsonPath('data.needs_review.0.citable_id', $artist->id)
        ->assertJsonPath('data.needs_review.0.completeness_pct', 100)
        ->assertJsonCount(1, 'data.priority_queue');
});

it('returns an all-zero payload for a reviewer with nothing pending, not an error', function () {
    $reviewer = reviewerUser();

    $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer')->assertOk()
        ->assertJsonPath('data.summary.waiting_review', 0)
        ->assertJsonPath('data.summary.verification_issues', 0)
        ->assertJsonPath('data.summary.changes_returned', 0)
        ->assertJsonCount(0, 'data.needs_review')
        ->assertJsonCount(0, 'data.priority_queue');
});

it('returns an all-zero payload for a user with no review_queue permissions, not an error', function () {
    $editor = editorUser();

    $artist = Artist::factory()->complete()->create();
    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'review_type' => 'archivist_review',
        'status' => 'pending',
    ]);

    $this->actingAs($editor)->getJson('/api/v1/dashboard/reviewer')->assertOk()
        ->assertJsonPath('data.summary.waiting_review', 0)
        ->assertJsonCount(0, 'data.needs_review');
});

it('scopes the queue to only the review types the reviewer actually holds', function () {
    seedRoles();
    $reviewer = makeUser();
    $reviewer->givePermissionTo('review_queue.archivist_review');

    $archiveArtist = Artist::factory()->complete()->create();
    $materialArtist = Artist::factory()->complete()->create();

    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $archiveArtist->id,
        'review_type' => 'archivist_review',
        'status' => 'pending',
    ]);
    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class,
        'citable_id' => $materialArtist->id,
        'review_type' => 'material_intake',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer')->assertOk();

    $response->assertJsonPath('data.summary.waiting_review', 1)
        ->assertJsonPath('data.needs_review.0.citable_id', $archiveArtist->id);
});

it('distinguishes a first-time pending proposal from one returned for changes', function () {
    $reviewer = reviewerUser();
    $editor = editorUser();

    $freshArtist = Artist::factory()->complete()->create();
    $returnedArtist = Artist::factory()->complete()->create();

    EditProposal::create([
        'citable_type' => Artist::class,
        'citable_id' => $freshArtist->id,
        'proposed_by_user_id' => $editor->id,
        'status' => 'pending',
        'field_diffs' => ['bio_en' => ['old_value_at_proposal_time' => null, 'proposed_value' => 'New bio']],
        'rationale' => 'Adding a bio.',
        'review_type' => 'editorial_review',
        'review_note' => null,
    ]);

    EditProposal::create([
        'citable_type' => Artist::class,
        'citable_id' => $returnedArtist->id,
        'proposed_by_user_id' => $editor->id,
        'status' => 'pending',
        'field_diffs' => ['bio_en' => ['old_value_at_proposal_time' => null, 'proposed_value' => 'Revised bio']],
        'rationale' => 'Addressed feedback.',
        'review_type' => 'editorial_review',
        'review_note' => 'Please add a citation for the birth year.',
    ]);

    $response = $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer')->assertOk();

    $response->assertJsonPath('data.summary.changes_returned', 1)
        ->assertJsonCount(1, 'data.returned_content')
        ->assertJsonPath('data.returned_content.0.citable_id', $returnedArtist->id)
        ->assertJsonPath('data.pipeline.ready_for_review', 1)
        ->assertJsonPath('data.pipeline.changes_requested', 0);
});

it('surfaces an artist that is 100% complete but still needs verification, without calling it incomplete', function () {
    $reviewer = reviewerUser();

    $artist = Artist::factory()->complete()->create([
        'living_status' => 'deceased',
        'death_year_from' => 1990,
        'verified_status' => 'unverified',
    ]);

    $response = $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer')->assertOk();

    $response->assertJsonPath('data.summary.verification_issues', 1)
        ->assertJsonPath('data.verification_issues.0.id', $artist->id)
        ->assertJsonPath('data.verification_issues.0.completeness_pct', 100);
});

it('never accepts a user_id override for the caller\'s own review history', function () {
    $reviewer = reviewerUser();
    $other = reviewerUser();
    $editor = editorUser();

    $artist = Artist::factory()->complete()->create();
    EditProposal::create([
        'citable_type' => Artist::class,
        'citable_id' => $artist->id,
        'proposed_by_user_id' => $editor->id,
        'reviewed_by_user_id' => $other->id,
        'status' => 'approved',
        'field_diffs' => ['bio_en' => ['old_value_at_proposal_time' => null, 'proposed_value' => 'New bio']],
        'rationale' => 'Adding a bio.',
        'review_type' => 'editorial_review',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($reviewer)->getJson('/api/v1/dashboard/reviewer?user_id='.$other->id)->assertOk()
        ->assertJsonCount(0, 'data.recently_reviewed');
});
