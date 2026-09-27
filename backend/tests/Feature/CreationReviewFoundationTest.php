<?php

use App\Enums\ProposalStatus;
use App\Models\Artist;
use App\Models\ReviewQueueItem;
use App\Models\User;
use App\Support\Proposals\CreationReviewService;
use App\Support\Proposals\EditorialDraftService;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('starts a draft creation-review item alongside a new record', function () {
    $creator = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();

    $proposal = (new CreationReviewService)->startFor($artist, $creator);

    expect($proposal->is_creation)->toBeTrue()
        ->and($proposal->status)->toBe(ProposalStatus::Draft->value)
        ->and($proposal->citable_type)->toBe(Artist::class)
        ->and($proposal->citable_id)->toBe($artist->id)
        ->and($proposal->proposed_by_user_id)->toBe($creator->id)
        ->and($proposal->payload)->toBeNull()
        ->and($proposal->field_diffs)->toBe([]);
});

it('submits a creation-review item with no nothing-differs check, and it reaches the queue', function () {
    $creator = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();
    $service = new CreationReviewService;
    $proposal = $service->startFor($artist, $creator);

    $submitted = $service->submit($proposal, $artist, $creator);

    expect($submitted->status)->toBe(ProposalStatus::Pending->value)
        ->and($submitted->review_type)->not->toBeNull()
        ->and(ReviewQueueItem::where('edit_proposal_id', $submitted->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('approving a creation-review item stamps the record, closes the queue item, and logs it', function () {
    $creator = User::factory()->create();
    $reviewer = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();
    $service = new CreationReviewService;
    $proposal = $service->submit($service->startFor($artist, $creator), $artist, $creator);

    expect($artist->creation_approved_at)->toBeNull();

    $service->approve($proposal, $artist, $reviewer, 'looks good');

    $artist->refresh();
    $proposal->refresh();
    expect($artist->creation_approved_at)->not->toBeNull()
        ->and($proposal->status)->toBe(ProposalStatus::Approved->value)
        ->and($proposal->reviewed_by_user_id)->toBe($reviewer->id)
        ->and(ReviewQueueItem::where('edit_proposal_id', $proposal->id)->where('status', 'acknowledged')->exists())->toBeTrue()
        ->and(Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)->where('event', 'creation_approved')->exists())->toBeTrue();
});

it('finds the open creation-review item for a record, across draft/pending/changes_requested', function () {
    $creator = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();
    $service = new CreationReviewService;
    $proposal = $service->startFor($artist, $creator);

    expect($service->openFor($artist)?->id)->toBe($proposal->id);

    $service->submit($proposal, $artist, $creator);
    expect($service->openFor($artist)?->id)->toBe($proposal->id);

    $proposal->refresh()->update(['status' => 'changes_requested']);
    expect($service->openFor($artist)?->id)->toBe($proposal->id);

    $proposal->refresh()->update(['status' => 'approved']);
    expect($service->openFor($artist))->toBeNull();
});

it('a resubmission after changes-requested reaches the queue again', function () {
    $creator = User::factory()->create();
    $reviewer = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();
    $service = new CreationReviewService;
    $proposal = $service->submit($service->startFor($artist, $creator), $artist, $creator);

    // Request-changes for a creation item goes through the same, unmodified
    // EditorialDraftService::requestChanges() used for edits (it's purely
    // status-flipping, no payload involved either way).
    (new EditorialDraftService)->requestChanges($proposal, $reviewer, 'add a bio');

    $resubmitted = $service->submit($proposal->refresh(), $artist, $creator);

    expect($resubmitted->id)->toBe($proposal->id)
        ->and($resubmitted->status)->toBe(ProposalStatus::Pending->value)
        ->and(ReviewQueueItem::where('edit_proposal_id', $proposal->id)->where('status', 'pending')->count())->toBe(1);
});

it('refuses to submit a creation-review item that is already pending or approved', function () {
    $creator = User::factory()->create();
    $artist = Artist::factory()->unreviewed()->create();
    $service = new CreationReviewService;
    $proposal = $service->submit($service->startFor($artist, $creator), $artist, $creator);

    $this->expectException(HttpException::class);
    $service->submit($proposal, $artist, $creator);
});
