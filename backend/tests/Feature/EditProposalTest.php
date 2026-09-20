<?php

use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\FieldCitation;
use App\Models\ReviewQueueItem;
use App\Models\Revision;
use App\Models\Source;
use Spatie\Activitylog\Models\Activity;

function proposableArtist(array $overrides = []): Artist
{
    return Artist::factory()->create(array_merge([
        'name_ar' => 'أحمد', 'name_en' => 'Ahmad', 'bio_en' => null, 'living_status' => 'unknown',
    ], $overrides));
}

function propose(Artist $artist, $user, array $changes, string $rationale = 'Found it in the catalogue.')
{
    return test()->actingAs($user)->postJson("/api/v1/records/artists/{$artist->id}/proposals", [
        'changes' => $changes, 'rationale' => $rationale,
    ]);
}

it('captures a contributor edit as a proposal and never touches the live record', function () {
    $contributor = makeUser('contributor');
    $artist = proposableArtist();

    $id = propose($artist, $contributor, ['name_en' => 'Ahmad Almaghlout'])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.field_diffs.name_en.old_value_at_proposal_time', 'Ahmad')
        ->assertJsonPath('data.field_diffs.name_en.proposed_value', 'Ahmad Almaghlout')
        ->json('data.id');

    expect($artist->refresh()->name_en)->toBe('Ahmad')
        ->and(Revision::where('citable_id', $artist->id)->count())->toBe(0)
        ->and(EditProposal::find($id)->review_type)->toBe('second_source_needed');

    // The contributor has no direct write path at all.
    $this->actingAs($contributor)->patchJson("/api/v1/artists/{$artist->id}", ['name' => ['en' => 'Sneaky']])->assertForbidden();
});

it('records an editor direct edit as a direct_edit revision', function () {
    $editor = editorUser();
    $artist = proposableArtist();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['name' => ['ar' => 'أحمد', 'en' => 'Ahmad Almaghlout']])->assertOk();

    $revision = Revision::where('citable_id', $artist->id)->sole();
    expect($revision->source)->toBe('direct_edit')
        ->and($revision->revision_number)->toBe(1)
        ->and($revision->applied_by_user_id)->toBe($editor->id)
        ->and($revision->field_diffs['name_en']['old'])->toBe('Ahmad')
        ->and($revision->field_diffs['name_en']['new'])->toBe('Ahmad Almaghlout');
});

it('recomputes the diff server-side and refuses fields that are not proposable', function () {
    $contributor = makeUser('contributor');
    $artist = proposableArtist();

    // A client-supplied "old" value is ignored entirely; only `changes` is read.
    $body = ['changes' => ['bio_en' => 'A painter.'], 'rationale' => 'From the card.', 'field_diffs' => ['bio_en' => ['old_value_at_proposal_time' => 'LIES', 'proposed_value' => 'LIES']]];
    $diffs = $this->actingAs($contributor)->postJson("/api/v1/records/artists/{$artist->id}/proposals", $body)
        ->assertCreated()->json('data.field_diffs');
    expect($diffs)->toBe(['bio_en' => ['old_value_at_proposal_time' => null, 'proposed_value' => 'A painter.']]);

    propose($artist, $contributor, ['publication_status' => 'published'])->assertUnprocessable();
    propose($artist, $contributor, ['search_text' => 'x'])->assertUnprocessable();
    propose($artist, $contributor, ['name_en' => 'Ahmad'])->assertUnprocessable(); // identical to current
});

it('applies an approved proposal through the normal update path: audit log, completeness and search all fire', function () {
    $editor = editorUser();
    $contributor = makeUser('contributor');
    $artist = proposableArtist();
    $before = (float) DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct');

    $id = propose($artist, $contributor, ['name_en' => 'Ahmad Almaghlout', 'bio_en' => 'A Saudi painter.'])->json('data.id');
    Auth::forgetGuards();

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/approve", ['review_note' => 'Checked.'])
        ->assertOk()->assertJsonPath('data.status', 'approved');

    $artist->refresh();
    expect($artist->name_en)->toBe('Ahmad Almaghlout')->and($artist->bio_en)->toBe('A Saudi painter.');

    // 1. LogsChanges recorded the write, attributed to the reviewer, carrying the proposal's provenance.
    $activity = Activity::where('subject_type', Artist::class)->where('subject_id', $artist->id)->where('event', 'updated')->latest('id')->first();
    expect($activity->attribute_changes['attributes']['name_en'])->toBe('Ahmad Almaghlout')
        ->and($activity->causer_id)->toBe((string) $editor->id)
        ->and($activity->properties['edit_summary'])->toContain($id);

    // 2. F10 recomputed completeness.
    expect((float) DB::table('record_completeness')->where('citable_id', $artist->id)->value('completeness_pct'))->toBeGreaterThan($before);

    // 3. The search column was rebuilt.
    expect($artist->search_text)->toContain('almaghlout');

    $proposal = EditProposal::find($id);
    $revision = Revision::find($proposal->resulting_revision_id);
    expect($proposal->status)->toBe('approved')->and($revision->source)->toBe('approved_proposal')
        ->and($revision->edit_proposal_id)->toBe($id)
        ->and(ReviewQueueItem::where('edit_proposal_id', $id)->value('status'))->toBe('acknowledged');
});

it('blocks approval when the field drifted, and applies against the current value once confirmed', function () {
    $editor = editorUser();
    $artist = proposableArtist();
    $id = propose($artist, makeUser('contributor'), ['bio_en' => 'From the proposal.'])->json('data.id');
    Auth::forgetGuards();

    $artist->update(['bio_en' => 'Someone else got here first.']);

    $conflict = $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/approve")->assertStatus(409)->json('conflicts');
    expect($conflict[0])->toMatchArray([
        'field' => 'bio_en', 'proposed_against' => null,
        'current' => 'Someone else got here first.', 'proposed_value' => 'From the proposal.',
    ]);
    expect($artist->refresh()->bio_en)->toBe('Someone else got here first.');

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/approve", ['confirm_conflict' => true])->assertOk();

    expect($artist->refresh()->bio_en)->toBe('From the proposal.');
    // The revision records the value actually replaced, not the stale one.
    $revision = Revision::where('citable_id', $artist->id)->where('source', 'approved_proposal')->sole();
    expect($revision->field_diffs['bio_en']['old'])->toBe('Someone else got here first.');
});

it('supersedes only the pending proposals that overlap the approved fields', function () {
    $editor = editorUser();
    $contributor = makeUser('contributor');
    $artist = proposableArtist();

    $first = propose($artist, $contributor, ['bio_en' => 'First take.'])->json('data.id');
    $overlapping = propose($artist, $contributor, ['bio_en' => 'Second take.'])->json('data.id');
    $disjoint = propose($artist, $contributor, ['nationality_en' => 'Saudi'])->json('data.id');
    Auth::forgetGuards();

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$first}/approve")->assertOk();

    expect(EditProposal::find($overlapping)->status)->toBe('superseded')
        ->and(EditProposal::find($disjoint)->status)->toBe('pending')
        ->and(ReviewQueueItem::where('edit_proposal_id', $overlapping)->value('status'))->toBe('dismissed');

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$disjoint}/approve")->assertOk();
    expect($artist->refresh()->nationality_en)->toBe('Saudi')
        ->and($this->actingAs($editor)->postJson("/api/v1/proposals/{$overlapping}/approve")->status())->toBe(422);
});

it('leaves the record untouched when a proposal is rejected, and keeps the proposal with its reason', function () {
    $editor = editorUser();
    $artist = proposableArtist();
    $snapshot = $artist->only(['name_ar', 'name_en', 'bio_en', 'living_status']);
    $id = propose($artist, makeUser('contributor'), ['bio_en' => 'Unsourced claim.'])->json('data.id');
    Auth::forgetGuards();

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/reject")->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/reject", ['review_note' => 'No source given.'])->assertOk();

    expect($artist->refresh()->only(['name_ar', 'name_en', 'bio_en', 'living_status']))->toBe($snapshot)
        ->and(Revision::where('citable_id', $artist->id)->count())->toBe(0);

    $proposal = EditProposal::find($id);
    expect($proposal->status)->toBe('rejected')->and($proposal->review_note)->toBe('No source given.');
});

it('promotes a proposal citation only on approval', function () {
    $editor = editorUser();
    $artist = proposableArtist();
    $source = Source::factory()->create();

    $id = $this->actingAs(makeUser('contributor'))->postJson("/api/v1/records/artists/{$artist->id}/proposals", [
        'changes' => ['bio_en' => 'A painter from Alahsa.'], 'rationale' => 'Page 12.',
        'citations' => [['field_key' => 'bio_en', 'source_id' => $source->id, 'claimed_value' => 'A painter from Alahsa.']],
    ])->assertCreated()->json('data.id');
    Auth::forgetGuards();

    expect(FieldCitation::where('citable_id', $artist->id)->count())->toBe(0);

    $this->actingAs($editor)->postJson("/api/v1/proposals/{$id}/approve")->assertOk();
    $citation = FieldCitation::where('citable_id', $artist->id)->sole();
    expect($citation->field_key)->toBe('bio_en')->and($citation->source_id)->toBe($source->id);
});

it('rolls back by adding a reversing revision rather than deleting history', function () {
    $editor = editorUser();
    $artist = proposableArtist();

    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['bio' => ['en' => 'Version two.']])->assertOk();
    $revision = Revision::where('citable_id', $artist->id)->sole();

    $new = $this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$revision->id}/rollback")
        ->assertOk()->json('data');

    expect($artist->refresh()->bio_en)->toBeNull()
        ->and($new['source'])->toBe('rollback')
        ->and($new['revision_number'])->toBe(2)
        ->and(Revision::where('citable_id', $artist->id)->count())->toBe(2)
        ->and($revision->refresh()->reverted_by_revision_id)->toBe($new['id']);

    $history = $this->actingAs($editor)->getJson("/api/v1/records/artists/{$artist->id}/revisions")->assertOk()->json('data');
    expect($history)->toHaveCount(2)->and($history[0]['revision_number'])->toBe(2);

    // A revision that has already been reverted cannot be reverted again.
    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$revision->id}/rollback")->assertStatus(422);
});

it('requires explicit confirmation when a rollback would unpublish a live record', function () {
    $editor = editorUser();
    $artist = proposableArtist(['living_status' => 'unknown']);
    FieldCitation::factory()->create(['citable_type' => Artist::class, 'citable_id' => $artist->id, 'field_key' => 'name', 'source_id' => Source::factory()->create()->id]);

    // Confirming the artist is living clears the last blocking gap, so it can be published.
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['living_status' => 'living'])->assertOk();
    $this->actingAs($editor)->patchJson("/api/v1/artists/{$artist->id}", ['publication_status' => 'published'])->assertOk();

    $revision = Revision::where('citable_id', $artist->id)->where('revision_number', 1)->sole();

    $blocked = $this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$revision->id}/rollback")->assertStatus(409);
    expect($blocked->json('would_unpublish'))->toBeTrue()
        ->and($blocked->json('blocking'))->toContain('death_year_or_living_confirmed');
    expect($artist->refresh()->living_status)->toBe('living')
        ->and($artist->publication_status)->toBe('published');

    $this->actingAs($editor)->postJson("/api/v1/records/artists/{$artist->id}/revisions/{$revision->id}/rollback", ['confirm_unpublish' => true])->assertOk();

    $artist->refresh();
    expect($artist->living_status)->toBe('unknown')->and($artist->publication_status)->toBe('draft');
});

it('routes proposals into F10 queue with the right review type and leaves its role scoping unchanged', function () {
    $contributor = makeUser('contributor');
    $artist = proposableArtist();

    propose($artist, $contributor, ['rights_holder_ar' => 'x'])->assertUnprocessable(); // not an artist field
    $claim = propose($artist, $contributor, ['bio_en' => 'Claim.'])->json('data.review_type');
    $measured = propose($artist, $contributor, ['birth_year_from' => 1953])->json('data.review_type');
    $plain = propose($artist, $contributor, ['nationality_en' => 'Saudi'])->json('data.review_type');
    expect([$claim, $measured, $plain])->toBe(['second_source_needed', 'data_audit', 'editorial_review']);

    Auth::forgetGuards();
    $queue = $this->actingAs(editorUser())->getJson('/api/v1/review-queue')->assertOk()->json('data');
    expect(collect($queue)->pluck('review_type')->sort()->values()->all())->toBe(['data_audit', 'editorial_review', 'second_source_needed']);

    // A reader still sees nothing: F10's role scoping is untouched.
    Auth::forgetGuards();
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/review-queue')->assertOk()->assertJsonCount(0, 'data');
});

it('lets a contributor see only their own proposals and reviewers see all', function () {
    $mine = makeUser('contributor');
    $other = makeUser('contributor');
    $artist = proposableArtist();
    $id = propose($artist, $mine, ['bio_en' => 'Mine.'])->json('data.id');
    Auth::forgetGuards();
    propose($artist, $other, ['nationality_en' => 'Saudi'])->json('data.id');
    Auth::forgetGuards();

    $this->actingAs($mine)->getJson('/api/v1/proposals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    Auth::forgetGuards();
    $this->actingAs(editorUser())->getJson('/api/v1/proposals')->assertOk()->assertJsonCount(2, 'data');
    Auth::forgetGuards();
    $this->actingAs($other)->getJson("/api/v1/proposals/{$id}")->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs($mine)->getJson("/api/v1/proposals/{$id}")->assertOk()->assertJsonPath('data.rationale', 'Found it in the catalogue.');
});
