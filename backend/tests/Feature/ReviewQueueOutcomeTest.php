<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\ReviewQueueItem;

it('records an approve outcome on a standalone queue entry', function () {
    $reviewer = reviewerUser();
    $item = ReviewQueueItem::factory()->create([
        'citable_type' => ArchiveItem::class,
        'citable_id' => ArchiveItem::factory()->create()->id,
        'review_type' => 'archivist_review',
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/v1/review-queue/{$item->id}/outcome", ['outcome' => 'approved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.is_proposal_backed', false)
        ->assertJsonPath('data.acted_by.id', $reviewer->id);

    expect($item->refresh()->status)->toBe('approved')
        ->and($item->acted_by_user_id)->toBe($reviewer->id)
        ->and($item->acted_at)->not->toBeNull();
});

it('requires a review note when rejecting', function () {
    $item = ReviewQueueItem::factory()->create([
        'citable_type' => ArchiveItem::class,
        'citable_id' => ArchiveItem::factory()->create()->id,
        'review_type' => 'archivist_review',
    ]);

    $this->actingAs(reviewerUser())
        ->postJson("/api/v1/review-queue/{$item->id}/outcome", ['outcome' => 'rejected'])
        ->assertUnprocessable();

    $this->actingAs(reviewerUser())
        ->postJson("/api/v1/review-queue/{$item->id}/outcome", ['outcome' => 'rejected', 'review_note' => 'Scan is illegible'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', 'Scan is illegible');
});

it('rejects outcomes on proposal-backed entries and on terminal entries', function () {
    $reviewer = reviewerUser();
    $artist = Artist::factory()->create();
    $proposal = EditProposal::create([
        'citable_type' => Artist::class, 'citable_id' => $artist->id,
        'proposed_by_user_id' => $reviewer->id, 'status' => 'pending',
        'field_diffs' => [], 'rationale' => 'x', 'review_type' => 'data_audit',
    ]);
    $proposalBacked = ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class, 'citable_id' => $artist->id,
        'review_type' => 'data_audit', 'edit_proposal_id' => $proposal->id,
    ]);
    $terminal = ReviewQueueItem::factory()->create([
        'review_type' => 'archivist_review', 'status' => 'approved',
    ]);

    $this->actingAs($reviewer)
        ->postJson("/api/v1/review-queue/{$proposalBacked->id}/outcome", ['outcome' => 'approved'])
        ->assertUnprocessable();

    $this->actingAs($reviewer)
        ->postJson("/api/v1/review-queue/{$terminal->id}/outcome", ['outcome' => 'rejected', 'review_note' => 'too late'])
        ->assertConflict();
});

it('forbids outcome recording without the entry review permission', function () {
    $item = ReviewQueueItem::factory()->create(['review_type' => 'archivist_review']);

    $this->actingAs(editorUser())
        ->postJson("/api/v1/review-queue/{$item->id}/outcome", ['outcome' => 'approved'])
        ->assertForbidden();
});

it('filters the queue index by status and exposes proposal linkage', function () {
    $reviewer = reviewerUser();
    $artist = Artist::factory()->create();
    $proposal = EditProposal::create([
        'citable_type' => Artist::class, 'citable_id' => $artist->id,
        'proposed_by_user_id' => $reviewer->id, 'status' => 'pending',
        'field_diffs' => [], 'rationale' => 'x', 'review_type' => 'data_audit',
    ]);
    ReviewQueueItem::factory()->create([
        'citable_type' => Artist::class, 'citable_id' => $artist->id,
        'review_type' => 'data_audit', 'edit_proposal_id' => $proposal->id,
    ]);
    ReviewQueueItem::factory()->create(['review_type' => 'archivist_review', 'status' => 'approved']);

    $pending = $this->actingAs($reviewer)->getJson('/api/v1/review-queue')->assertOk()->json('data');
    expect($pending)->toHaveCount(1)
        ->and($pending[0]['is_proposal_backed'])->toBeTrue()
        ->and($pending[0]['edit_proposal_id'])->toBe($proposal->id);

    $approved = $this->actingAs($reviewer)->getJson('/api/v1/review-queue?status=approved')->assertOk()->json('data');
    expect($approved)->toHaveCount(1)->and($approved[0]['status'])->toBe('approved');
});
