<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\File;
use App\Models\MaterialSubmission;
use App\Models\MaterialSubmissionFile;
use App\Models\ReviewQueueItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => Storage::fake('local'));

function submissionBody(array $overrides = []): array
{
    return array_merge([
        'submitter_name' => 'Abdullah Al Marzook', 'submitter_contact' => '0505 867 193',
        'submitter_role' => 'artist_family', 'city' => 'Saihat',
        'description' => 'Twelve photographs from the late 1970s.', 'attestation' => '1',
    ], $overrides);
}

function pdf(string $name = 'letter.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** A genuine upload: unlike the framework's fake, this one is judged by its bytes, as in production. */
function realUpload(string $name, string $content, string $claimedType): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, $claimedType, null, true);
}

function submit(array $overrides = [], array $files = [])
{
    return test()->postJson('/api/v1/material-submissions', array_merge(submissionBody($overrides), $files === [] ? [] : ['files' => $files]));
}

it('lets an anonymous visitor submit and queues it for intake review, returning nothing but an id and status', function () {
    $res = submit(files: [UploadedFile::fake()->image('a.jpg'), pdf()])->assertCreated();

    expect($res->json('data'))->toBe(['id' => $res->json('data.id'), 'status' => 'submitted']);

    $submission = MaterialSubmission::findOrFail($res->json('data.id'));
    expect($submission->status)->toBe('submitted')->and($submission->files)->toHaveCount(2);
    $files = $submission->files->pluck('mime_type')->sort()->values()->all();
    expect($files)->toBe(['application/pdf', 'image/jpeg']);
    Storage::disk('local')->assertExists($submission->files->first()->path);

    $queued = ReviewQueueItem::where('citable_type', MaterialSubmission::class)->where('citable_id', $submission->id)->sole();
    expect($queued->review_type)->toBe('material_intake')->and($queued->status)->toBe('pending');
});

it('accepts a description-only report with no files', function () {
    submit(files: [])->assertCreated();
    expect(MaterialSubmission::sole()->files)->toHaveCount(0);
});

it('requires the identifying fields, a known role and the attestation', function () {
    test()->postJson('/api/v1/material-submissions', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['submitter_name', 'submitter_contact', 'submitter_role', 'description', 'attestation']);
    submit(['submitter_role' => 'stranger'])->assertJsonValidationErrors(['submitter_role']);
    submit(['attestation' => '0'])->assertJsonValidationErrors(['attestation']);

    // Contact is free text on purpose: a phone number, an email or a mix all go through.
    submit(['submitter_contact' => 'call after 5pm, ext 12'])->assertCreated();
});

it('judges files by their content, not the type the client claims', function () {
    // Claims to be a JPEG, is actually a script.
    $liar = realUpload('photo.jpg', '<?php echo "not an image";', 'image/jpeg');
    submit(files: [$liar])->assertUnprocessable()->assertJsonValidationErrors(['files.0']);

    // A genuine type the platform does not accept.
    submit(files: [realUpload('page.pdf', '<html><body>hi</body></html>', 'application/pdf')])->assertUnprocessable();

    expect(MaterialSubmission::count())->toBe(0);

    // A genuine PDF is accepted even under a misleading name and claim: the content decides.
    $real = realUpload('scan.jpg', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'image/jpeg');
    submit(files: [$real])->assertCreated();
    expect(MaterialSubmissionFile::sole()->mime_type)->toBe('application/pdf');
});

it('caps a submission at 2 GB in total with a specific message', function () {
    $errors = submit(files: [
        UploadedFile::fake()->create('a.pdf', 1_500_000, 'application/pdf'),
        UploadedFile::fake()->create('b.pdf', 1_500_000, 'application/pdf'),
    ])->assertUnprocessable()->json('errors.files');

    expect(implode(' ', $errors))->toContain('2 GB');
    expect(MaterialSubmission::count())->toBe(0);
});

it('rate-limits a sixth submission from one address within the hour, but not from another', function () {
    foreach (range(1, 5) as $n) {
        submit()->assertCreated();
    }
    submit()->assertStatus(429);

    test()->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->postJson('/api/v1/material-submissions', submissionBody())->assertCreated();
});

it('never links a submission to an artist on its own, even when the submitter names one', function () {
    $artist = Artist::factory()->create(['name_ar' => 'عبدالله المرزوق', 'name_en' => 'Abdullah Al Marzook']);

    $id = submit(['submitter_name' => 'Abdullah Al Marzook', 'submitter_role' => 'artist'])->json('data.id');

    $submission = MaterialSubmission::findOrFail($id);
    expect($submission->linked_artist_id)->toBeNull()->and($submission->linked_artwork_id)->toBeNull();

    // Only an explicit staff action sets it.
    $this->actingAs(editorUser())->patchJson("/api/v1/material-submissions/{$id}", ['linked_artist_id' => $artist->id])->assertOk();
    expect($submission->refresh()->linked_artist_id)->toBe($artist->id);
});

it('gates every staff endpoint on materials.review', function () {
    $id = submit()->json('data.id');

    foreach ([['getJson', '/api/v1/material-submissions'], ['getJson', "/api/v1/material-submissions/{$id}"], ['patchJson', "/api/v1/material-submissions/{$id}"],
        ['postJson', "/api/v1/material-submissions/{$id}/catalog"], ['postJson', "/api/v1/material-submissions/{$id}/reject"]] as [$method, $url]) {
        test()->$method($url)->assertUnauthorized();
    }

    Auth::forgetGuards();
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/material-submissions')->assertForbidden();
    Auth::forgetGuards();
    $this->actingAs(makeUser('contributor'))->postJson("/api/v1/material-submissions/{$id}/reject", ['staff_notes' => 'nope'])->assertForbidden();
    Auth::forgetGuards();

    $this->actingAs(editorUser())->getJson('/api/v1/material-submissions')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.submitter_contact', '0505 867 193');
});

it('catalogs into non-public archive items, moving the staged files and recording provenance', function () {
    $editor = editorUser();
    $artist = Artist::factory()->create();
    $id = submit(files: [UploadedFile::fake()->image('a.jpg', 300, 200), pdf('b.pdf')])->json('data.id');
    $stagedPaths = MaterialSubmissionFile::where('material_submission_id', $id)->pluck('path')->all();
    [$first, $second] = MaterialSubmissionFile::where('material_submission_id', $id)->orderBy('id')->pluck('id')->all();

    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'initial_review', 'linked_artist_id' => $artist->id])->assertOk();

    $res = $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", ['items' => [
        ['item_type' => 'image', 'title' => ['ar' => 'صورة'], 'file_ids' => [$first]],
        ['item_type' => 'document', 'title' => ['en' => 'A letter'], 'file_ids' => [$second]],
    ]])->assertCreated();

    expect($res->json('data.status'))->toBe('authorization_pending')->and($res->json('data.created_archive_item_ids'))->toHaveCount(2);

    $items = ArchiveItem::where('source_material_submission_id', $id)->orderBy('id')->get();
    expect($items)->toHaveCount(2);
    foreach ($items as $item) {
        // The non-negotiable default, whatever the submitter said.
        expect($item->access_level)->toBe('institution_only')->and($item->publication_status)->toBe('draft')
            ->and($item->links()->where('role', 'donor')->where('linkable_id', $artist->id)->exists())->toBeTrue();
    }

    $file = File::where('archive_item_id', $items[0]->id)->sole();
    expect($file->role)->toBe('original')->and($file->width_px)->toBe(300)->and($file->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists($file->path);

    // Moved, not copied: the staging rows and the staged bytes are gone.
    expect(MaterialSubmissionFile::where('material_submission_id', $id)->count())->toBe(0);
    foreach ($stagedPaths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    expect(ReviewQueueItem::where('citable_id', $id)->where('citable_type', MaterialSubmission::class)->value('status'))->toBe('acknowledged');
});

it('refuses to catalog with unassigned, foreign or duplicated files, or from the wrong state', function () {
    $editor = editorUser();
    $id = submit(files: [pdf('a.pdf'), pdf('b.pdf')])->json('data.id');
    $other = submit(files: [pdf('c.pdf')])->json('data.id');
    [$a, $b] = MaterialSubmissionFile::where('material_submission_id', $id)->orderBy('id')->pluck('id')->all();
    $foreign = MaterialSubmissionFile::where('material_submission_id', $other)->value('id');
    $item = fn (array $ids) => ['items' => [['item_type' => 'document', 'title' => ['ar' => 'x'], 'file_ids' => $ids]]];

    // Still `submitted`: nobody has started the review.
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", $item([$a, $b]))->assertStatus(422);

    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'initial_review'])->assertOk();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", $item([$a]))->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", $item([$a, $b, $foreign]))->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", ['items' => [
        ['item_type' => 'document', 'title' => ['ar' => 'x'], 'file_ids' => [$a, $b]],
        ['item_type' => 'document', 'title' => ['ar' => 'y'], 'file_ids' => [$a]],
    ]])->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", ['items' => [['item_type' => 'document', 'title' => [], 'file_ids' => [$a, $b]]]])->assertUnprocessable();

    expect(ArchiveItem::where('source_material_submission_id', $id)->count())->toBe(0)
        ->and(MaterialSubmissionFile::where('material_submission_id', $id)->count())->toBe(2);
});

it('rejects a submission by deleting its files and bytes but keeping the record and the reason', function () {
    $editor = editorUser();
    $id = submit(files: [UploadedFile::fake()->image('a.jpg')])->json('data.id');
    $path = MaterialSubmissionFile::where('material_submission_id', $id)->value('path');
    Storage::disk('local')->assertExists($path);

    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/reject")->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/reject", ['staff_notes' => 'Not related to Saudi art.'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    Storage::disk('local')->assertMissing($path);
    expect(MaterialSubmissionFile::where('material_submission_id', $id)->count())->toBe(0);

    $kept = MaterialSubmission::findOrFail($id);
    expect($kept->status)->toBe('rejected')->and($kept->staff_notes)->toBe('Not related to Saudi art.')->and($kept->submitter_name)->toBe('Abdullah Al Marzook');
    expect(ReviewQueueItem::where('citable_id', $id)->where('citable_type', MaterialSubmission::class)->value('status'))->toBe('dismissed');

    // A closed submission cannot be reopened or re-rejected.
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'initial_review'])->assertUnprocessable();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/reject", ['staff_notes' => 'again'])->assertUnprocessable();
});

it('publishes only from authorization_pending and only with a signed letter, and never jumps ahead', function () {
    $editor = editorUser();
    $id = submit(files: [pdf()])->json('data.id');
    $fileId = MaterialSubmissionFile::where('material_submission_id', $id)->value('id');

    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'published'])->assertUnprocessable();
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'authorization_pending'])->assertUnprocessable();
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'initial_review'])->assertOk();
    $this->actingAs($editor)->postJson("/api/v1/material-submissions/{$id}/catalog", ['items' => [['item_type' => 'document', 'title' => ['ar' => 'x'], 'file_ids' => [$fileId]]]])->assertCreated();

    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'published'])->assertUnprocessable();
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['authorization_letter_status' => 'signed'])->assertOk();
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'published'])->assertOk()->assertJsonPath('data.status', 'published');

    // Publishing the submission does not publish the items: they stay drafts for a person to release.
    expect(ArchiveItem::where('source_material_submission_id', $id)->value('publication_status'))->toBe('draft');
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'withdrawn'])->assertUnprocessable();
});

it('shows intake submissions in the F10 review queue for staff who can review them, and keeps the submitter out of the audit log', function () {
    $id = submit(['submitter_name' => 'Secret Donor Name', 'submitter_contact' => 'secret@example.com'])->json('data.id');
    Auth::forgetGuards();

    $queue = $this->actingAs(editorUser())->getJson('/api/v1/review-queue')->assertOk()->json('data');
    expect(collect($queue)->firstWhere('review_type', 'material_intake'))->toMatchArray([
        'citable_id' => $id, 'title' => ['ar' => "مادة مقدَّمة #{$id}", 'en' => "Material submission #{$id}"],
    ]);
    Auth::forgetGuards();
    $this->actingAs(makeUser('reader'))->getJson('/api/v1/review-queue')->assertOk()->assertJsonCount(0, 'data');

    $editor = editorUser();
    $this->actingAs($editor)->patchJson("/api/v1/material-submissions/{$id}", ['status' => 'initial_review', 'staff_notes' => 'Called back.'])->assertOk();
    $log = json_encode(Activity::where('subject_type', MaterialSubmission::class)->get()->map->attribute_changes);
    expect($log)->toContain('initial_review')->not->toContain('secret@example.com')->not->toContain('Secret Donor Name');
});
