<?php

use App\Enums\ReviewType;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\EditProposal;
use App\Models\Event;
use App\Models\ReviewQueueItem;
use App\Models\Revision;
use Spatie\Activitylog\Models\Activity;

function draftableArtist(array $overrides = []): Artist
{
    return Artist::factory()->create(array_merge([
        'name_ar' => 'أحمد', 'name_en' => 'Ahmad', 'bio_en' => null, 'living_status' => 'unknown',
    ], $overrides));
}

/**
 * A full artist draft touching every section.
 *
 * @return array<string, mixed>
 */
function artistDraftPayload(array $overrides = []): array
{
    return array_merge([
        'fields' => ['name' => ['en' => 'Ahmad Almaghlout'], 'bio' => ['en' => 'A Saudi painter.']],
        'curation' => ['owner_type' => 'gallery', 'contacts' => [['name' => 'Nora', 'email' => 'nora@gallery.test']]],
        'educations' => [['title' => ['en' => 'BFA'], 'place' => ['en' => 'Cairo Academy'], 'year_from' => 1960, 'year_to' => 1964]],
        'activities' => [
            ['type' => 'award', 'title' => ['en' => 'Prize'], 'year_from' => 1988],
            ['type' => 'exhibition', 'title' => ['en' => 'Solo show'], 'place' => ['en' => 'Dar Al-Funun'], 'year_from' => 1978],
        ],
        'social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/ahmad', 'is_public' => true]],
    ], $overrides);
}

function upsertDraft(string $type, int $id, $user, array $payload, ?string $rationale = 'Staging a fuller profile.')
{
    return test()->actingAs($user)->putJson("/api/v1/records/{$type}/{$id}/draft", [
        'payload' => $payload,
        'rationale' => $rationale,
    ]);
}

function submitDraft(string $type, int $id, $user)
{
    return test()->actingAs($user)->postJson("/api/v1/records/{$type}/{$id}/draft/submit");
}

it('lets an editor stage a sectioned draft without touching the live record', function () {
    $editor = editorUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $editor, artistDraftPayload())
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payload.curation.owner_type', 'gallery')
        ->json('data.id');

    // The editor reads their own draft back, payload intact.
    $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/draft")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.payload.fields.name.en', 'Ahmad Almaghlout')
        ->assertJsonPath('data.payload.social_links.0.platform', 'instagram');

    // A different editor sees no draft of their own.
    Auth::forgetGuards();
    $this->actingAs(editorUser())->getJson("/api/v1/records/artists/{$artist->id}/draft")
        ->assertOk()->assertJsonPath('data', null);

    // Live record is untouched.
    $artist->refresh();
    expect($artist->name_en)->toBe('Ahmad')
        ->and($artist->bio_en)->toBeNull()
        ->and($artist->entries()->count())->toBe(0)
        ->and($artist->contacts()->count())->toBe(0)
        ->and($artist->socialLinks()->count())->toBe(0)
        ->and(Revision::where('citable_id', $artist->id)->count())->toBe(0);
});

it('validates every section payload like the endpoint bodies do', function () {
    $editor = editorUser();
    $artist = draftableArtist();

    upsertDraft('artists', $artist->id, $editor, artistDraftPayload([
        'educations' => [['title' => ['en' => 'BFA'], 'year_from' => 1990, 'year_to' => 1980]],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['educations.0.year_to']);

    upsertDraft('artists', $artist->id, $editor, artistDraftPayload([
        'social_links' => [['platform' => 'instagram', 'url' => 'not a url']],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['social_links.0.url']);

    upsertDraft('artists', $artist->id, $editor, artistDraftPayload([
        'curation' => ['owner_type' => 'nobody'],
    ]))->assertUnprocessable()->assertJsonValidationErrors(['curation.owner_type']);

    expect(EditProposal::count())->toBe(0);
});

it('lets a draft resend the artist\'s own unchanged legacy_code without a false uniqueness failure', function () {
    $editor = editorUser();
    $artist = draftableArtist(['legacy_code' => 'AR036']);

    upsertDraft('artists', $artist->id, $editor, artistDraftPayload([
        'fields' => ['name' => ['en' => 'Ahmad Almaghlout'], 'legacy_code' => 'AR036'],
    ]))
        ->assertOk()
        ->assertJsonPath('data.payload.fields.legacy_code', 'AR036');
});

it('keeps one open draft per record: another editor gets a 409, the owner can replace theirs', function () {
    $owner = editorUser();
    $other = editorUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $owner, ['fields' => ['bio' => ['en' => 'First take.']]])
        ->assertOk()->json('data.id');

    Auth::forgetGuards();
    upsertDraft('artists', $artist->id, $other, ['fields' => ['bio' => ['en' => 'Blocked.']]])
        ->assertStatus(409)
        ->assertJsonPath('proposal_id', $id);
    expect(EditProposal::count())->toBe(1);

    Auth::forgetGuards();
    upsertDraft('artists', $artist->id, $owner, ['fields' => ['bio' => ['en' => 'Replacement.']]])
        ->assertOk()->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.payload.fields.bio.en', 'Replacement.');
    expect(EditProposal::count())->toBe(1);
});

it('submits a draft to the review queue and refuses an empty one', function () {
    $editor = editorUser();
    $artist = draftableArtist();

    upsertDraft('artists', $artist->id, $editor, ['fields' => ['name' => ['ar' => 'أحمد', 'en' => 'Ahmad']]])
        ->assertOk();

    // Identical to the live value: nothing differs.
    submitDraft('artists', $artist->id, $editor)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payload']);

    Auth::forgetGuards();
    upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'Actually new.']]])
        ->assertOk();

    $id = submitDraft('artists', $artist->id, $editor)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.field_diffs.bio_en.proposed_value', 'Actually new.')
        ->json('data.id');

    expect(EditProposal::find($id)->review_type)->toBe('second_source_needed')
        ->and(ReviewQueueItem::where('edit_proposal_id', $id)->sole()->status)->toBe('pending');

    // A submitted draft cannot be edited again until reviewed.
    Auth::forgetGuards();
    upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'Too late.']]])
        ->assertStatus(409);
});

it('applies an approved draft through the sections services: fields, entries, links, contacts and one revision', function () {
    $proposer = editorUser();
    $reviewer = reviewerUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $proposer, artistDraftPayload(), 'Fuller profile from the archive.')->json('data.id');
    submitDraft('artists', $artist->id, $proposer)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$id}/approve", ['review_note' => 'All sourced.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.resulting_revision_id', fn ($v) => $v !== null);

    $artist->refresh();
    expect($artist->name_en)->toBe('Ahmad Almaghlout')
        ->and($artist->bio_en)->toBe('A Saudi painter.')
        ->and($artist->owner_type)->toBe('gallery')
        ->and($artist->entries()->count())->toBe(3)
        ->and($artist->socialLinks()->sole()->url)->toBe('https://instagram.com/ahmad')
        ->and($artist->contacts()->sole()->name)->toBe('Nora');

    // Exhibition lines still mirror into the events registry.
    expect(Event::where('event_type', 'exhibition')->count())->toBe(1)
        ->and(Event::where('event_type', 'exhibition')->sole()->title_en)->toBe('Solo show');

    // Exactly one revision, attributed to the proposal and the reviewer.
    $revision = Revision::where('citable_id', $artist->id)->sole();
    expect($revision->source)->toBe('approved_proposal')
        ->and($revision->edit_proposal_id)->toBe($id)
        ->and($revision->applied_by_user_id)->toBe($reviewer->id)
        ->and($revision->field_diffs['name_en']['new'])->toBe('Ahmad Almaghlout')
        ->and(ReviewQueueItem::where('edit_proposal_id', $id)->value('status'))->toBe('acknowledged');

    // The contacts activity row carries counts only — no plaintext values.
    $activity = Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)
        ->where('description', 'contacts changed')->sole();
    expect($activity->properties['contacts_changed'])->toMatchArray(['created' => 1])
        ->and(json_encode($activity->properties))->not->toContain('nora@gallery.test')
        ->and($activity->causer_id)->toBe((string) $reviewer->id);
});

it('requests changes, lets the editor revise and resubmit with a fresh queue item', function () {
    $editor = editorUser();
    $reviewer = reviewerUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'First draft.']]])->json('data.id');
    submitDraft('artists', $artist->id, $editor)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$id}/request-changes")->assertUnprocessable();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'Source the birth year.'])
        ->assertOk()->assertJsonPath('data.status', 'changes_requested');

    $proposal = EditProposal::find($id);
    expect($proposal->review_note)->toBe('Source the birth year.')
        ->and(ReviewQueueItem::where('edit_proposal_id', $id)->sole()->status)->toBe('dismissed');

    // The editor still sees the note while revising.
    Auth::forgetGuards();
    $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/draft")
        ->assertOk()->assertJsonPath('data.review_note', 'Source the birth year.');

    upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'Revised with a source.']]])
        ->assertOk()->assertJsonPath('data.status', 'changes_requested');

    Auth::forgetGuards();
    submitDraft('artists', $artist->id, $editor)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.review_note', null);

    expect(ReviewQueueItem::where('edit_proposal_id', $id)->count())->toBe(2)
        ->and(ReviewQueueItem::where('edit_proposal_id', $id)->where('status', 'pending')->count())->toBe(1);
});

it('forbids self-review and locks the draft endpoints to submitters and reviewers to managers', function () {
    $editor = editorUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'Mine.']]])->json('data.id');
    submitDraft('artists', $artist->id, $editor)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/approve")->assertForbidden();
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/reject", ['review_note' => 'My own.'])->assertForbidden();
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'My own.'])->assertForbidden();

    Auth::forgetGuards();
    $reader = makeUser('reader');
    upsertDraft('artists', $artist->id, $reader, ['fields' => ['bio' => ['en' => 'Sneaky.']]])->assertForbidden();
    submitDraft('artists', $artist->id, $reader)->assertForbidden();

    // A contributor may propose but never reviews.
    Auth::forgetGuards();
    $contributor = makeUser('contributor');
    $this->actingAs($contributor)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'Not mine to judge.'])->assertForbidden();
});

it('scopes request-changes to holders of the proposal\'s own review_queue permission', function () {
    $proposer = editorUser();
    $artwork = Artwork::factory()->create(['title_en' => 'Old title']);
    $wrongQueue = makeUser();
    $wrongQueue->givePermissionTo('review_queue.data_audit');

    $id = upsertDraft('artworks', $artwork->id, $proposer, ['fields' => ['title' => ['en' => 'New title']]])
        ->assertOk()->json('data.id');
    submitDraft('artworks', $artwork->id, $proposer)->assertOk();
    expect(EditProposal::find($id)->review_type)->toBe('second_source_needed');

    Auth::forgetGuards();
    $this->actingAs($wrongQueue)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'Needs work.'])
        ->assertForbidden();

    Auth::forgetGuards();
    $rightQueue = makeUser();
    $rightQueue->givePermissionTo('review_queue.second_source_needed');
    $this->actingAs($rightQueue)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'Needs work.'])
        ->assertOk()->assertJsonPath('data.status', 'changes_requested');
});

it('serves a per-section diff to the proposer and managers only', function () {
    $editor = editorUser();
    $reviewer = reviewerUser();
    $outsider = makeUser();
    $outsider->givePermissionTo('artworks.manage');
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $editor, artistDraftPayload())->json('data.id');
    submitDraft('artists', $artist->id, $editor)->assertOk();

    // The reviewer holding this proposal's review_type sees flat field diffs
    // and child-collection diffs for every touched section.
    Auth::forgetGuards();
    $sections = $this->actingAs($reviewer)->getJson("/api/v1/proposals/{$id}/diff")
        ->assertOk()
        ->json('data.sections');

    expect($sections[0]['key'])->toBe('fields')
        ->and(collect($sections[0]['fields'])->firstWhere('field', 'name_en'))
        ->toMatchArray(['old' => 'Ahmad', 'new' => 'Ahmad Almaghlout'])
        ->and($sections[2]['key'])->toBe('educations')
        ->and($sections[2]['collections'][0]['key'])->toBe('educations')
        ->and($sections[2]['collections'][0]['added'][0]['fields'][0]['field'])->toBe('type')
        ->and($sections[3]['collections'][0]['added'][0]['label'])->toBe('Prize')
        ->and($sections[4]['collections'][0]['added'][0]['fields'][0]['field'])->toBe('platform');

    // The proposer may read their own diff…
    Auth::forgetGuards();
    $this->actingAs($editor)->getJson("/api/v1/proposals/{$id}/diff")->assertOk();

    // …but a signed-in user holding only another type's manage permission may not.
    Auth::forgetGuards();
    $this->actingAs($outsider)->getJson("/api/v1/proposals/{$id}/diff")->assertForbidden();

    // An empty payload proposal diffs to no sections.
    $blank = upsertDraft('artworks', Artwork::factory()->create()->id, $editor, ['fields' => []])
        ->assertOk()->json('data.id');
    Auth::forgetGuards();
    $this->actingAs($editor)->getJson("/api/v1/proposals/{$blank}/diff")
        ->assertOk()
        ->assertJsonPath('data.sections', [['key' => 'fields', 'fields' => [], 'collections' => []]]);
});

it('lets a review-queue permission holder read the diff and request changes without the manage permission', function () {
    $editor = editorUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $editor, ['fields' => ['bio' => ['en' => 'Queue-visible draft.']]])->json('data.id');
    submitDraft('artists', $artist->id, $editor)->assertOk();

    $queueReviewer = makeUser('reviewer');
    $queueReviewer->givePermissionTo(ReviewType::from(EditProposal::find($id)->review_type)->permission());

    Auth::forgetGuards();
    $this->actingAs($queueReviewer)->getJson("/api/v1/proposals/{$id}/diff")
        ->assertOk()
        ->assertJsonPath('data.sections.0.key', 'fields');

    Auth::forgetGuards();
    $this->actingAs($queueReviewer)->postJson("/api/v1/proposals/{$id}/request-changes", ['review_note' => 'Tighten the wording.'])
        ->assertOk()->assertJsonPath('data.status', 'changes_requested');
});

it('applies an event draft with fields and participants on approval', function () {
    $proposer = editorUser();
    $reviewer = reviewerUser();
    $artist = draftableArtist();
    $event = Event::create(['event_type' => 'exhibition', 'title_ar' => 'معرض']);

    $id = upsertDraft('events', $event->id, $proposer, [
        'fields' => ['title' => ['en' => 'The Big Show'], 'venue_name' => 'Dar Al-Funun'],
        'participants' => [['type' => 'artist', 'participant_id' => $artist->id, 'role' => 'participant']],
    ])->assertOk()->json('data.id');
    submitDraft('events', $event->id, $proposer)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$id}/approve")->assertOk();

    $event->refresh();
    expect($event->title_en)->toBe('The Big Show')
        ->and($event->venue_name)->toBe('Dar Al-Funun')
        ->and($event->participants()->count())->toBe(1)
        ->and($event->participants()->sole()->participant_id)->toBe($artist->id);

    expect(Revision::where('citable_id', $event->id)->sole()->source)->toBe('approved_proposal');
});

it('applies artwork and archive-item field drafts on approval', function () {
    $proposer = editorUser();
    $reviewer = reviewerUser();

    $artwork = Artwork::factory()->create(['title_en' => 'Old title']);
    $artworkId = upsertDraft('artworks', $artwork->id, $proposer, [
        'fields' => ['title' => ['en' => 'A New Title'], 'medium' => ['en' => 'Oil on canvas']],
    ])->assertOk()->json('data.id');
    submitDraft('artworks', $artwork->id, $proposer)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$artworkId}/approve")->assertOk();
    expect($artwork->refresh()->title_en)->toBe('A New Title')
        ->and(Revision::where('citable_id', $artwork->id)->sole()->source)->toBe('approved_proposal');

    Auth::forgetGuards();
    $item = ArchiveItem::factory()->create(['title_en' => 'Old item title']);
    $itemId = upsertDraft('archive-items', $item->id, $proposer, [
        'fields' => ['title' => ['en' => 'A New Item Title']],
    ])->assertOk()->json('data.id');
    submitDraft('archive-items', $item->id, $proposer)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$itemId}/approve")->assertOk();
    expect($item->refresh()->title_en)->toBe('A New Item Title')
        ->and(Revision::where('citable_id', $item->id)->sole()->source)->toBe('approved_proposal');
});

it('still rolls back the revision an approved draft produced', function () {
    $proposer = editorUser();
    $reviewer = reviewerUser();
    $artist = draftableArtist();

    $id = upsertDraft('artists', $artist->id, $proposer, ['fields' => ['bio' => ['en' => 'Version two.']]])->json('data.id');
    submitDraft('artists', $artist->id, $proposer)->assertOk();

    Auth::forgetGuards();
    $this->actingAs($reviewer)->postJson("/api/v1/proposals/{$id}/approve")->assertOk();

    $revision = Revision::where('citable_id', $artist->id)->sole();
    // Rolling back is a content-modifying action (artists.manage), not review work.
    Auth::forgetGuards();
    $new = $this->actingAs($proposer)
        ->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$revision->id}/rollback")
        ->assertOk()->json('data');

    expect($artist->refresh()->bio_en)->toBeNull()
        ->and($new['source'])->toBe('rollback')
        ->and(Revision::where('citable_id', $artist->id)->count())->toBe(2);
});
