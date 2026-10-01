<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\ArtistContact;
use App\Models\ArtistContactProposal;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Models\OcrHandwritingSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Per-value contact proposals from an authorization letter: where each value
 * came from, the state it is in, and why a stale one can't quietly apply.
 * Synthetic values only — the benchmark letter's real details never appear in tests.
 */

/**
 * A letter whose phone was handwritten (read by a handwriting model, accepted
 * by a reviewer) and whose email was printed — and looks struck through.
 *
 * @return array{0: ArchiveItem, 1: File, 2: FileOcrRegion}
 */
function letterWithRegions(): array
{
    $item = ArchiveItem::factory()->create(['legacy_ref' => 'AR900']);
    $file = File::factory()->create(['archive_item_id' => $item->id, 'document_type' => 'artist_authorization']);
    $region = fn (int $page, string $type, int $confidence, array $extra = []) => FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => $page, 'region_type' => $type, 'language' => 'ar',
        'bbox' => ['x' => 120, 'y' => 640, 'width' => 380, 'height' => 44], 'confidence' => $confidence, ...$extra,
    ]);

    $phoneRegion = $region(2, 'handwriting', 38, ['crop_path' => 'ocr/crops/phone.png', 'crop_sha256' => str_repeat('a', 64)]);
    $emailRegion = $region(1, 'printed_text', 91, ['has_correction_mark' => true]);

    FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'اسم الفنان', 'requires_manual_transcription' => true, 'manual_value' => 'سارة الراشد']);
    FileOcrFormField::create([
        'file_id' => $file->id, 'field_label' => 'رقم الجوال', 'value_region_id' => $phoneRegion->id,
        'requires_manual_transcription' => true, 'manual_value' => '0500000001', 'transcribed_at' => now(),
    ]);
    FileOcrFormField::create([
        'file_id' => $file->id, 'field_label' => 'البريد الإلكتروني', 'value_region_id' => $emailRegion->id,
        'machine_value' => 'sara@example.test', 'requires_manual_transcription' => false, 'has_correction_mark' => true,
    ]);
    OcrHandwritingSuggestion::create([
        'file_id' => $file->id, 'region_id' => $phoneRegion->id, 'crop_path' => 'ocr/crops/phone.png', 'crop_sha256' => str_repeat('a', 64),
        'provider' => 'kraken', 'model' => 'arabic-hw', 'model_version' => '1.0', 'status' => 'suggested',
        'text' => '0500000001', 'confidence' => 0.62, 'decision' => 'accepted', 'final_text' => '0500000001', 'decided_at' => now(),
    ]);

    return [$item, $file, $phoneRegion];
}

function letterArtist(): Artist
{
    $artist = Artist::factory()->create(['name_ar' => 'سارة الراشد', 'name_en' => 'Sara Alrashed']);
    $artist->contacts()->create(['name' => 'Studio', 'email' => 'studio@example.test', 'phone' => '0500000002', 'sort' => 0]);

    return $artist;
}

function letterUrl(ArchiveItem $item, string $suffix = ''): string
{
    return "/api/v1/archive-items/{$item->id}/file/ocr/artist-contact{$suffix}";
}

/** Confirms the artist and proposes, as the given editor. */
function proposeFromLetter(ArchiveItem $item, Artist $artist, User $editor, array $values): void
{
    test()->actingAs($editor)->putJson(letterUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();
    test()->actingAs($editor)->postJson(letterUrl($item, '/propose'), $values)->assertOk();
}

/** Switches the acting user between requests. */
function actAs(User $user): User
{
    Auth::forgetGuards();
    test()->actingAs($user);

    return $user;
}

it('records where each value came from: page, region, method, confidence and the handwriting model that read it', function () {
    [$item, , $phoneRegion] = letterWithRegions();
    $editor = editorUser();
    proposeFromLetter($item, letterArtist(), $editor, ['name' => 'سارة الراشد', 'phone' => '0500000001', 'email' => 'sara@example.test']);

    $values = collect($this->actingAs($editor)->getJson(letterUrl($item))->assertOk()->json('data.contact_proposals'))->keyBy('field');

    expect($values['phone'])->toMatchArray([
        'action' => 'new_contact', 'status' => 'pending', 'proposed_value' => '0500000001',
        'extraction_method' => 'manually_transcribed', 'confidence' => null, 'edited_by_proposer' => false, 'has_correction_mark' => false,
        'machine_suggestion' => ['provider' => 'kraken', 'model' => 'arabic-hw', 'model_version' => '1.0', 'confidence' => 0.62, 'decision' => 'accepted'],
    ])
        ->and($values['phone']['source'])->toMatchArray(['page' => 2, 'region_id' => $phoneRegion->id, 'label' => 'رقم الجوال', 'has_crop' => true])
        ->and($values['phone']['proposed_by']['id'])->toBe($editor->id)
        ->and($values['email'])->toMatchArray(['extraction_method' => 'ocr_derived', 'confidence' => 91, 'has_correction_mark' => true, 'machine_suggestion' => null])
        ->and($values['email']['source']['page'])->toBe(1);

    // Re-running OCR replaces regions; the provenance was copied, so it outlives them.
    $phoneRegion->delete();
    $phone = collect($this->getJson(letterUrl($item))->json('data.contact_proposals'))->firstWhere('field', 'phone');
    expect($phone['source'])->toMatchArray(['page' => 2, 'region_id' => null, 'has_crop' => false, 'bbox' => ['x' => 120, 'y' => 640, 'width' => 380, 'height' => 44]]);
});

it('follows the draft to approval, recording the reviewer and when', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    proposeFromLetter($item, $artist, editorUser(), ['phone' => '0500000001']);
    $reviewer = actAs(reviewerUser());

    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/approve')->assertOk();

    $value = ArtistContactProposal::sole();
    expect($value->status)->toBe('approved')
        ->and($value->reviewed_by_user_id)->toBe($reviewer->id)
        ->and($value->reviewed_at)->not->toBeNull()
        ->and($artist->contacts()->count())->toBe(2);
});

it('follows a rejection, keeping the reviewer\'s note', function () {
    [$item] = letterWithRegions();
    proposeFromLetter($item, letterArtist(), editorUser(), ['phone' => '0500000001']);
    actAs(reviewerUser());

    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/reject', ['review_note' => 'Last digit unclear on the scan.'])->assertOk();

    expect(ArtistContactProposal::sole())->status->toBe('rejected')->review_note->toBe('Last digit unclear on the scan.');
});

it('supersedes the earlier values when the proposer proposes again after changes are requested', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    $editor = editorUser();
    proposeFromLetter($item, $artist, $editor, ['phone' => '0500000001']);
    $first = ArtistContactProposal::sole();

    actAs(reviewerUser());
    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/request-changes', ['review_note' => 'Add the email too.'])->assertOk();
    expect($first->refresh())->status->toBe('changes_requested')->review_note->toBe('Add the email too.');

    actAs($editor);
    $this->postJson(letterUrl($item, '/propose'), ['phone' => '0500000001', 'email' => 'sara@example.test'])->assertOk();

    expect($first->refresh())->status->toBe('superseded')->superseded_reason->toBe('newer_proposal')->review_note->toBe('Add the email too.')
        ->and(ArtistContactProposal::where('status', 'pending')->pluck('field')->sort()->values()->all())->toBe(['email', 'phone']);

    // The reviewer sees only what the draft now carries.
    actAs(reviewerUser());
    $evidence = $this->getJson('/api/v1/proposals/'.EditProposal::sole()->id.'/diff')->assertOk()->json('data.evidence');
    expect($evidence)->toHaveCount(1)
        ->and(collect($evidence[0]['values'])->pluck('status')->unique()->all())->toBe(['pending']);
});

it('supersedes the values when their draft is rewritten on the artist page', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    $editor = editorUser();
    proposeFromLetter($item, $artist, $editor, ['phone' => '0500000001']);
    actAs(reviewerUser());
    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/request-changes', ['review_note' => 'Check the number.'])->assertOk();

    actAs($editor);
    $this->putJson("/api/v1/records/artists/{$artist->id}/draft", ['payload' => ['fields' => ['bio' => ['en' => 'Rewritten by hand.']]]])->assertOk();

    expect(ArtistContactProposal::sole())->status->toBe('superseded')->superseded_reason->toBe('draft_rewritten');
});

it('supersedes the values when their draft is superseded', function () {
    [$item] = letterWithRegions();
    proposeFromLetter($item, letterArtist(), editorUser(), ['phone' => '0500000001']);

    EditProposal::sole()->update(['status' => 'superseded']);

    expect(ArtistContactProposal::sole())->status->toBe('superseded')->superseded_reason->toBe('other_proposal_approved');
});

it('makes the reviewer confirm before approving over contacts that changed since the proposal', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    proposeFromLetter($item, $artist, editorUser(), ['phone' => '0500000001']);
    $proposalId = EditProposal::sole()->id;

    // Someone corrects the existing contact directly after the proposal was made.
    $artist->contacts()->sole()->update(['email' => 'studio-new@example.test']);

    actAs(reviewerUser());
    $this->getJson("/api/v1/proposals/{$proposalId}/diff")->assertOk()
        ->assertJsonPath('data.conflicts.0.field', 'contacts')
        ->assertJsonPath('data.conflicts.0.collection', true)
        ->assertJsonPath('data.conflicts.0.current', null);
    $this->postJson("/api/v1/proposals/{$proposalId}/approve")->assertStatus(409)->assertJsonPath('conflicts.0.field', 'contacts');

    // Nothing applied, and the direct correction stands.
    expect($artist->contacts()->count())->toBe(1)
        ->and($artist->contacts()->sole()->email)->toBe('studio-new@example.test')
        ->and(ArtistContactProposal::sole()->status)->toBe('pending');

    // Approving anyway is an explicit decision.
    $this->postJson("/api/v1/proposals/{$proposalId}/approve", ['confirm_conflict' => true])->assertOk();
    expect(ArtistContactProposal::sole()->status)->toBe('approved');
});

it('protects a contacts draft written by hand on the artist page the same way', function () {
    $artist = letterArtist();
    $contact = $artist->contacts()->sole();
    $editor = editorUser();
    $this->actingAs($editor)->putJson("/api/v1/records/artists/{$artist->id}/draft", ['payload' => ['curation' => ['contacts' => [
        ['id' => $contact->id, 'name' => 'Studio', 'email' => 'studio@example.test', 'phone' => '0500000003'],
    ]]]])->assertOk();
    $this->postJson("/api/v1/records/artists/{$artist->id}/draft/submit")->assertOk();

    $contact->update(['name' => 'Studio (main)']);

    actAs(reviewerUser());
    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/approve')->assertStatus(409);
    expect($contact->refresh()->name)->toBe('Studio (main)');
});

it('shows the value it would replace only to someone who can see contacts, and only until it is decided', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    $contact = $artist->contacts()->sole();
    $editor = editorUser();
    $this->actingAs($editor)->putJson(letterUrl($item, '/artist'), ['artist_id' => $artist->id])->assertOk();

    // The email the contact already holds is not a change, so it isn't proposed.
    $this->postJson(letterUrl($item, '/propose'), ['phone' => '0500000001', 'email' => 'studio@example.test', 'target_contact_id' => $contact->id])->assertOk()
        ->assertJsonCount(1, 'data.contact_proposals')
        ->assertJsonPath('data.contact_proposals.0.field', 'phone')
        ->assertJsonPath('data.contact_proposals.0.action', 'update_contact')
        ->assertJsonPath('data.contact_proposals.0.replaces_existing', true)
        ->assertJsonPath('data.contact_proposals.0.current_value', '0500000002')
        ->assertJsonPath('data.contact_proposals.0.current_value_shown', true);

    actAs(tap(User::factory()->create())->givePermissionTo('archive.manage'));
    $this->getJson(letterUrl($item))->assertOk()
        ->assertJsonPath('data.contact_proposals.0.current_value', null)
        ->assertJsonPath('data.contact_proposals.0.current_value_shown', false)
        ->assertJsonPath('data.contact_proposals.0.proposed_value', '0500000001');

    actAs(reviewerUser());
    $this->postJson('/api/v1/proposals/'.EditProposal::sole()->id.'/reject', ['review_note' => 'Not the artist\'s number.'])->assertOk();
    actAs($editor);
    $this->getJson(letterUrl($item))->assertJsonPath('data.contact_proposals.0.current_value', null)
        ->assertJsonPath('data.contact_proposals.0.current_value_shown', false);
});

it('gives the approving reviewer the document behind each value, and nothing for a draft not made from one', function () {
    [$item, $file] = letterWithRegions();
    $artist = letterArtist();
    proposeFromLetter($item, $artist, editorUser(), ['phone' => '0500000001']);

    actAs(reviewerUser());
    $this->getJson('/api/v1/proposals/'.EditProposal::sole()->id.'/diff')->assertOk()
        ->assertJsonPath('data.conflicts', [])
        ->assertJsonPath('data.evidence.0.file_id', $file->id)
        ->assertJsonPath('data.evidence.0.archive_item.legacy_ref', 'AR900')
        ->assertJsonPath('data.evidence.0.can_open_document', true)
        ->assertJsonPath('data.evidence.0.values.0.field', 'phone')
        ->assertJsonPath('data.evidence.0.values.0.source.page', 2)
        ->assertJsonPath('data.evidence.0.values.0.current_value', null);

    $other = Artist::factory()->create();
    $editor = actAs(editorUser());
    $this->putJson("/api/v1/records/artists/{$other->id}/draft", ['payload' => ['fields' => ['bio' => ['en' => 'By hand.']]]])->assertOk();
    $this->postJson("/api/v1/records/artists/{$other->id}/draft/submit")->assertOk();
    $handMade = EditProposal::where('citable_id', $other->id)->sole();
    expect($handMade->proposed_by_user_id)->toBe($editor->id);
    $this->getJson("/api/v1/proposals/{$handMade->id}/diff")->assertOk()->assertJsonPath('data.evidence', null);
});

it('never writes a contact while a value is only proposed', function () {
    [$item] = letterWithRegions();
    $artist = letterArtist();
    $before = ArtistContact::query()->get()->toArray();

    proposeFromLetter($item, $artist, editorUser(), ['phone' => '0500000001', 'address' => 'Riyadh, Example Street 1']);

    expect(ArtistContact::query()->get()->toArray())->toBe($before)
        ->and(ArtistContactProposal::pluck('field')->sort()->values()->all())->toBe(['address', 'phone']);
});
