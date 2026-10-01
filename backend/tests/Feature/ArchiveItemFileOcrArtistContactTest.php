<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileOcrContactProposal;
use App\Models\FileOcrFormField;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * An archive item whose file was classified as an authorization letter, with
 * the benchmark's form fields: a reviewer-transcribed name and phone, and an
 * email label that was never detected (so email has no form field at all).
 *
 * @return array{0: ArchiveItem, 1: File}
 */
function authorizationLetter(string $documentType = 'artist_authorization'): array
{
    $item = ArchiveItem::factory()->create(['legacy_ref' => 'AR036']);
    $file = File::factory()->create(['archive_item_id' => $item->id, 'document_type' => $documentType]);
    FileOcrFormField::create(['file_id' => $file->id, 'field_label' => '3 الفنان/ة', 'requires_manual_transcription' => true, 'manual_value' => 'أحمد المغلوث', 'transcribed_at' => now()]);
    FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'دك الجوال', 'requires_manual_transcription' => true, 'manual_value' => '0500000001', 'transcribed_at' => now()]);

    return [$item, $file];
}

function contactArtist(): Artist
{
    $artist = Artist::factory()->create(['name_ar' => 'أحمد المغلوث', 'name_en' => 'Ahmad Almaghlout']);
    $artist->contacts()->create(['name' => 'Gallery desk', 'email' => 'desk@gallery.test', 'sort' => 0]);

    return $artist;
}

function archiveOnlyUser(): User
{
    seedRoles();

    return tap(User::factory()->create())->givePermissionTo(['archive.manage', 'proposals.submit']);
}

function contactUrl(ArchiveItem $item, string $suffix = ''): string
{
    return "/api/v1/archive-items/{$item->id}/file/ocr/artist-contact{$suffix}";
}

it('pre-fills identity and contacts from transcribed form fields and suggests the matching artist', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();

    $this->actingAs(editorUser())->getJson(contactUrl($item))->assertOk()
        ->assertJsonPath('data.applicable', true)
        ->assertJsonPath('data.extracted.artist_name.value', 'أحمد المغلوث')
        ->assertJsonPath('data.extracted.artist_name.method', 'manually_transcribed')
        ->assertJsonPath('data.extracted.phone.value', '0500000001')
        ->assertJsonPath('data.extracted.email.form_field_id', null)
        ->assertJsonPath('data.candidates.0.artist.id', $artist->id)
        ->assertJsonPath('data.candidates.0.strength', 'high')
        ->assertJsonPath('data.confirmed_artist', null)
        ->assertJsonPath('data.proposal', null);
});

it('lets the reviewer search candidates under a different spelling', function () {
    [$item] = authorizationLetter();
    $other = Artist::factory()->create(['name_ar' => 'صفية بن زقر', 'name_en' => 'Safeya Binzagr']);

    $this->actingAs(editorUser())->getJson(contactUrl($item).'?name=Safeya%20Binzagr')->assertOk()
        ->assertJsonPath('data.search_name', 'Safeya Binzagr')
        ->assertJsonPath('data.candidates.0.artist.id', $other->id);
});

it('confirms the artist without touching the artist or its contacts', function () {
    [$item, $file] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $before = $artist->contacts()->get()->toArray();

    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk()
        ->assertJsonPath('data.confirmed_artist.id', $artist->id)
        ->assertJsonPath('data.confirmed_artist.confirmed_by_user_id', $editor->id)
        ->assertJsonPath('data.existing_contacts.0.email', 'desk@gallery.test');

    expect($artist->contacts()->get()->toArray())->toBe($before)
        ->and(EditProposal::count())->toBe(0)
        ->and(FileOcrContactProposal::where('file_id', $file->id)->value('artist_id'))->toBe($artist->id);
});

it('refuses the flow on a document not classified as an authorization letter', function () {
    [$item] = authorizationLetter('artwork_condition_report');

    $this->actingAs(editorUser())->putJson(contactUrl($item, '/artist'), ['artist_id' => contactArtist()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('document_type');
});

it('refuses to propose before an artist is confirmed', function () {
    [$item] = authorizationLetter();

    $this->actingAs(editorUser())->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])
        ->assertUnprocessable()->assertJsonValidationErrors('artist_id');
});

it('refuses a proposal carrying no contact value at all', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['name' => 'أحمد المغلوث'])
        ->assertUnprocessable()->assertJsonValidationErrors('values');
});

it('proposes a new contact as a pending archivist-review draft, carrying existing contacts over unchanged', function () {
    [$item, $file] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $res = $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), [
        'name' => 'أحمد المغلوث', 'phone' => '0500000001', 'email' => 'ahmad@example.test',
    ])->assertOk()
        ->assertJsonPath('data.proposal.status', 'pending')
        ->assertJsonPath('data.proposal.proposed_by_user_id', $editor->id);

    // The phone came straight from the reviewer's transcription; the email label was never detected, so it was typed at proposal time.
    $values = collect($res->json('data.contact_proposals'))->keyBy('field');
    expect($values->keys()->sort()->values()->all())->toBe(['email', 'phone'])
        ->and($values['phone'])->toMatchArray(['action' => 'new_contact', 'status' => 'pending', 'proposed_value' => '0500000001', 'extraction_method' => 'manually_transcribed', 'edited_by_proposer' => false, 'replaces_existing' => false])
        ->and($values['phone']['source']['label'])->toBe('دك الجوال')
        ->and($values['email'])->toMatchArray(['proposed_value' => 'ahmad@example.test', 'extraction_method' => 'manually_transcribed', 'edited_by_proposer' => true])
        ->and($values['email']['source']['label'])->toBeNull();

    $proposal = EditProposal::sole();
    expect($proposal->status)->toBe('pending')
        ->and($proposal->review_type)->toBe('archivist_review')
        ->and($proposal->rationale)->toContain('AR036')
        ->and($proposal->payload['curation']['contacts'])->toHaveCount(2)
        ->and($proposal->payload['curation']['contacts'][0]['id'])->toBe($artist->contacts()->sole()->id)
        ->and($proposal->payload['curation']['contacts'][1]['phone'])->toBe('0500000001');

    // Nothing reached the artist yet; the document's row never holds the values, and each proposed value is encrypted at rest.
    expect($artist->contacts()->count())->toBe(1)
        ->and(json_encode(DB::table('file_ocr_contact_proposals')->where('file_id', $file->id)->first()))->not->toContain('0500000001')
        ->and(json_encode(DB::table('artist_contact_proposals')->get()))->not->toContain('0500000001');
});

it('applies the contact only when a different reviewer approves, logging the change without values', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['name' => 'أحمد المغلوث', 'phone' => '0500000001'])->assertOk();
    $proposalId = EditProposal::sole()->id;

    // Neither the proposer nor an editor without the archivist queue can approve it.
    $this->actingAs($editor)->postJson("/api/v1/proposals/{$proposalId}/approve")->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs(editorUser())->postJson("/api/v1/proposals/{$proposalId}/approve")->assertForbidden();
    expect($artist->contacts()->count())->toBe(1);

    Auth::forgetGuards();
    $this->actingAs(reviewerUser())->postJson("/api/v1/proposals/{$proposalId}/approve")->assertOk();

    $contacts = $artist->contacts()->orderBy('sort')->get();
    expect($contacts)->toHaveCount(2)
        ->and($contacts[0]->email)->toBe('desk@gallery.test')
        ->and($contacts[1]->name)->toBe('أحمد المغلوث')
        ->and($contacts[1]->phone)->toBe('0500000001')
        ->and(DB::table('artist_contacts')->where('id', $contacts[1]->id)->value('phone'))->not->toBe('0500000001');

    $activity = Activity::where('description', 'contacts changed')->sole();
    expect($activity->properties['contacts_changed'])->toMatchArray(['created' => 1, 'updated' => 0, 'deleted' => 0])
        ->and(json_encode($activity->properties))->not->toContain('0500000001');

    Auth::forgetGuards();
    $this->actingAs($editor)->getJson(contactUrl($item))->assertJsonPath('data.proposal.status', 'approved');
});

it('updates only the confirmed values of a targeted existing contact', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $contact = $artist->contacts()->sole();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001', 'target_contact_id' => $contact->id])->assertOk()
        ->assertJsonPath('data.proposal.target_contact_id', $contact->id);

    expect(EditProposal::sole()->payload['curation']['contacts'])->toEqual([[
        'id' => $contact->id, 'name' => 'Gallery desk', 'role_note' => null, 'email' => 'desk@gallery.test', 'phone' => '0500000001', 'address' => null,
    ]]);
});

it('hides existing contact values from, and refuses targeting by, a user who cannot see artist contacts', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $user = archiveOnlyUser();
    $this->actingAs($user)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk()
        ->assertJsonPath('data.existing_contacts', null)
        ->assertJsonPath('data.can_target_existing', false);

    $this->actingAs($user)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001', 'target_contact_id' => $artist->contacts()->sole()->id])
        ->assertForbidden();
});

it('rejects a contact target belonging to another artist', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $foreign = Artist::factory()->create()->contacts()->create(['name' => 'Someone else', 'sort' => 0]);
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001', 'target_contact_id' => $foreign->id])
        ->assertUnprocessable()->assertJsonValidationErrors('target_contact_id');
});

it('reports the draft validator\'s errors under the form\'s own field names', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['email' => 'not an email'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(EditProposal::count())->toBe(0);
});

it('never overwrites a draft the reviewer started by hand on the artist page', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson("/api/v1/records/artists/{$artist->id}/draft", [
        'payload' => ['fields' => ['bio' => ['en' => 'Work in progress.']]],
    ])->assertOk();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])->assertStatus(409);

    expect(EditProposal::sole()->payload)->toBe(['fields' => ['bio' => ['en' => 'Work in progress.']]]);
});

it('locks the confirmed artist while its proposal is open, and allows a fresh proposal after rejection', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])->assertOk();

    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => Artist::factory()->create()->id])->assertStatus(409);
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000009'])->assertStatus(409);

    Auth::forgetGuards();
    $this->actingAs(reviewerUser())->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/reject', ['review_note' => 'Digits unclear on the scan.'])->assertOk();

    Auth::forgetGuards();
    $this->actingAs($editor)->getJson(contactUrl($item))->assertJsonPath('data.proposal.status', 'rejected')
        ->assertJsonPath('data.proposal.review_note', 'Digits unclear on the scan.');
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])->assertOk()
        ->assertJsonPath('data.proposal.status', 'pending');

    expect(EditProposal::count())->toBe(2);
});

it('resubmits its own draft after a reviewer requests changes', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])->assertOk();
    $proposalId = EditProposal::sole()->id;

    Auth::forgetGuards();
    $this->actingAs(reviewerUser())->postJson("/api/v1/proposals/{$proposalId}/request-changes", ['review_note' => 'Add the email too.'])->assertOk();

    Auth::forgetGuards();
    $this->actingAs($editor)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001', 'email' => 'ahmad@example.test'])->assertOk()
        ->assertJsonPath('data.proposal.status', 'pending');

    expect(EditProposal::sole()->id)->toBe($proposalId)
        ->and(EditProposal::sole()->payload['curation']['contacts'][1]['email'])->toBe('ahmad@example.test');
});

it('requires proposals.submit to propose', function () {
    [$item] = authorizationLetter();
    $artist = contactArtist();
    seedRoles();
    $user = tap(User::factory()->create())->givePermissionTo('archive.manage');
    $this->actingAs($user)->putJson(contactUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk()
        ->assertJsonPath('data.can_propose', false);

    $this->actingAs($user)->postJson(contactUrl($item, '/propose'), ['phone' => '0500000001'])->assertForbidden();
});
