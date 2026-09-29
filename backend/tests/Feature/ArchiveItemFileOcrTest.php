<?php

use App\Enums\ExtractedFieldStatus;
use App\Enums\FileOcrStatus;
use App\Enums\ProposalStatus;
use App\Jobs\ProcessFileOcrJob;
use App\Models\ArchiveItem;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileExtractedField;
use App\Models\FileExtractedText;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

function userWithOnlyArchiveManage(): User
{
    seedRoles();
    $user = makeUser();
    $user->givePermissionTo('archive.manage');

    return $user;
}

it('returns null when the item has no file yet', function () {
    $item = ArchiveItem::factory()->create();

    $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")
        ->assertOk()->assertJsonPath('data', null);
});

it('requires archive.manage to read OCR data', function () {
    $item = ArchiveItem::factory()->create();
    File::factory()->create(['archive_item_id' => $item->id]);

    $this->actingAs(makeUser('reader'))->getJson("/api/v1/archive-items/{$item->id}/file/ocr")->assertForbidden();
});

it('bundles OCR status, per-page texts, and candidate fields', function () {
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create([
        'archive_item_id' => $item->id,
        'ocr_status' => FileOcrStatus::Completed->value,
        'ocr_progress_pct' => 100,
        'ocr_language_confidence' => ['ar' => 90, 'en' => 80],
    ]);
    FileExtractedText::create(['file_id' => $file->id, 'page_number' => 1, 'language' => 'en', 'text' => 'Hello', 'confidence' => 88, 'segments' => [['text' => 'Hello', 'confidence' => 88]]]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'Hello', 'confidence' => 88, 'source_page' => 1, 'status' => ExtractedFieldStatus::Pending->value]);

    $res = $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")->assertOk();

    $res->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.language_confidence.ar', 90)
        ->assertJsonPath('data.texts.en.0.text', 'Hello')
        ->assertJsonPath('data.fields.0.id', $field->id)
        ->assertJsonPath('data.fields.0.field_key', 'title_en')
        ->assertJsonPath('data.fields.0.status', 'pending');
});

it("merges an accepted field into the reviewer's own draft instead of the live record", function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create(['title_en' => 'Original title']);
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'OCR title', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")
        ->assertOk()->assertJsonPath('data.status', 'accepted');

    expect($item->fresh()->title_en)->toBe('Original title');
    expect($field->fresh()->status)->toBe(ExtractedFieldStatus::Accepted)
        ->and($field->fresh()->reviewed_by_user_id)->toBe($editor->id);

    $proposal = EditProposal::where('citable_type', ArchiveItem::class)->where('citable_id', $item->id)->sole();
    expect($proposal->status)->toBe(ProposalStatus::Draft->value)
        ->and($proposal->payload['fields']['title']['en'])->toBe('OCR title');
});

it('merges into an existing open draft without discarding its other fields', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'source_name', 'extracted_value' => 'Family archive', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    EditProposal::create([
        'citable_type' => ArchiveItem::class, 'citable_id' => $item->id, 'proposed_by_user_id' => $editor->id,
        'status' => ProposalStatus::Draft->value, 'field_diffs' => [], 'review_type' => 'archivist_review',
        'payload' => ['fields' => ['description' => ['ar' => 'وصف مسبق', 'en' => null]]],
        'rationale' => '',
    ]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertOk();

    $proposal = EditProposal::where('citable_type', ArchiveItem::class)->where('citable_id', $item->id)->sole();
    expect($proposal->payload['fields']['description']['ar'])->toBe('وصف مسبق')
        ->and($proposal->payload['fields']['source_name'])->toBe('Family archive');
});

it('writes directly to the live record when the reviewer lacks proposals.submit', function () {
    $user = userWithOnlyArchiveManage();
    $item = ArchiveItem::factory()->create(['title_en' => 'Original title']);
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'OCR title', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($user)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertOk();

    expect($item->fresh()->title_en)->toBe('OCR title');
    expect(EditProposal::where('citable_type', ArchiveItem::class)->where('citable_id', $item->id)->exists())->toBeFalse();
});

it('rejects a field without touching the record', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create(['title_en' => 'Original title']);
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'OCR title', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/reject")
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    expect($item->fresh()->title_en)->toBe('Original title');
});

it('edits a suggested value without accepting it', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'OCR title', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->patchJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}", ['extracted_value' => 'Corrected title'])
        ->assertOk()->assertJsonPath('data.status', 'edited')->assertJsonPath('data.extracted_value', 'Corrected title');

    expect(EditProposal::where('citable_type', ArchiveItem::class)->where('citable_id', $item->id)->exists())->toBeFalse();
});

it('rejects accepting a field key with no known mapping onto the archive item', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $field = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'not_a_real_field', 'extracted_value' => 'x', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertUnprocessable();
});

it('404s when the field belongs to a different archive item', function () {
    $editor = editorUser();
    $itemA = ArchiveItem::factory()->create();
    $itemB = ArchiveItem::factory()->create();
    $fileB = File::factory()->create(['archive_item_id' => $itemB->id]);
    $field = FileExtractedField::create(['file_id' => $fileB->id, 'field_key' => 'title_en', 'extracted_value' => 'x', 'confidence' => 90, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$itemA->id}/file/ocr/fields/{$field->id}/accept")->assertNotFound();
});

it('bulk-accepts only fields at or above the confidence threshold', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $high = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'title_en', 'extracted_value' => 'High confidence', 'confidence' => 92, 'status' => ExtractedFieldStatus::Pending->value]);
    $low = FileExtractedField::create(['file_id' => $file->id, 'field_key' => 'source_name', 'extracted_value' => 'Low confidence', 'confidence' => 40, 'status' => ExtractedFieldStatus::Pending->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/accept-high-confidence")
        ->assertOk()->assertJsonPath('data.accepted.0', 'title_en')->assertJsonCount(1, 'data.accepted');

    expect($high->fresh()->status)->toBe(ExtractedFieldStatus::Accepted)
        ->and($low->fresh()->status)->toBe(ExtractedFieldStatus::Pending);
});

it('dispatches OCR on demand for a file that was never processed', function () {
    Queue::fake();
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'ocr_status' => null]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/run")
        ->assertOk()->assertJsonPath('data.status', 'pending');

    expect($file->fresh()->ocr_status)->toBe(FileOcrStatus::Pending);
    Queue::assertPushed(ProcessFileOcrJob::class, 1);
});

it('rejects running OCR on a file type that cannot be processed', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'audio/mpeg']);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/run")->assertUnprocessable();
});

it('rejects re-running OCR while it is already in progress', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    File::factory()->create(['archive_item_id' => $item->id, 'ocr_status' => FileOcrStatus::Processing->value]);

    $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/run")->assertStatus(409);
});

it('queues OCR processing when an image or PDF is uploaded, but not for other file types', function () {
    Queue::fake();
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();

    $this->actingAs($editor)->post("/api/v1/archive-items/{$item->id}/file", ['file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])->assertCreated();
    Queue::assertPushed(ProcessFileOcrJob::class, 1);

    $this->actingAs($editor)->post("/api/v1/archive-items/{$item->id}/file", ['file' => UploadedFile::fake()->create('letter.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();
    Queue::assertPushed(ProcessFileOcrJob::class, 2);

    $this->actingAs($editor)->post("/api/v1/archive-items/{$item->id}/file", ['file' => UploadedFile::fake()->create('note.mp3', 200, 'audio/mpeg')], ['Accept' => 'application/json'])->assertCreated();
    Queue::assertPushed(ProcessFileOcrJob::class, 2); // unchanged — audio isn't an OCR candidate
});
