<?php

use App\Enums\DateCalendar;
use App\Enums\ExtractedDateType;
use App\Enums\OcrRegionType;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileExtractedDate;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use Illuminate\Support\Facades\Storage;

it('bundles regions, form fields, dates, and document type alongside texts and fields', function () {
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'document_type' => 'artist_authorization']);
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 2, 'region_type' => OcrRegionType::Handwriting->value,
        'bbox' => ['x' => 10, 'y' => 20, 'width' => 100, 'height' => 30], 'confidence' => 22,
        'ocr_allowed' => false, 'ai_correction_allowed' => false, 'requires_human_review' => true,
        'review_reason' => 'Handwritten content requires manual transcription.',
    ]);
    FileOcrFormField::create([
        'file_id' => $file->id, 'field_label' => 'اسم الفنان/ة', 'value_region_id' => $region->id,
        'value_type' => 'handwriting', 'requires_manual_transcription' => true,
    ]);
    FileExtractedDate::create([
        'file_id' => $file->id, 'value' => '02/08/1445', 'calendar' => DateCalendar::Hijri->value,
        'date_type' => ExtractedDateType::DocumentIssueDate->value, 'source_page' => 1, 'source_method' => 'ocr',
    ]);

    $res = $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")->assertOk();

    $res->assertJsonPath('data.document_type', 'artist_authorization')
        ->assertJsonPath('data.regions.0.region_type', 'handwriting')
        ->assertJsonPath('data.regions.0.ocr_allowed', false)
        ->assertJsonPath('data.regions.0.requires_human_review', true)
        ->assertJsonPath('data.form_fields.0.field_label', 'اسم الفنان/ة')
        ->assertJsonPath('data.form_fields.0.requires_manual_transcription', true)
        ->assertJsonPath('data.dates.0.value', '02/08/1445')
        ->assertJsonPath('data.dates.0.calendar', 'hijri');
});

it('transcribes a form field manually, recording who and when, without touching the archive item', function () {
    $editor = editorUser();
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $formField = FileOcrFormField::create([
        'file_id' => $file->id, 'field_label' => 'رقم الجوال', 'requires_manual_transcription' => true,
    ]);

    $res = $this->actingAs($editor)->postJson(
        "/api/v1/archive-items/{$item->id}/file/ocr/form-fields/{$formField->id}/transcribe",
        ['value' => '0555555555']
    )->assertOk();

    $res->assertJsonPath('data.manual_value', '0555555555')
        ->assertJsonPath('data.transcribed_by_user_id', $editor->id);

    expect($formField->fresh()->manual_value)->toBe('0555555555')
        ->and($formField->fresh()->transcribed_at)->not->toBeNull();
});

it('404s transcribing a form field that belongs to a different archive item', function () {
    $itemA = ArchiveItem::factory()->create();
    $itemB = ArchiveItem::factory()->create();
    $fileB = File::factory()->create(['archive_item_id' => $itemB->id]);
    $formField = FileOcrFormField::create(['file_id' => $fileB->id, 'field_label' => 'x', 'requires_manual_transcription' => true]);

    $this->actingAs(editorUser())->postJson(
        "/api/v1/archive-items/{$itemA->id}/file/ocr/form-fields/{$formField->id}/transcribe",
        ['value' => 'x']
    )->assertNotFound();
});

it('serves a region crop image when one exists', function () {
    Storage::fake('local');
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'disk' => 'local']);
    Storage::disk('local')->put('archive/ocr-crops/crop.png', 'fake-png-bytes');
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => OcrRegionType::Signature->value,
        'bbox' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10], 'ocr_allowed' => false,
        'ai_correction_allowed' => false, 'requires_human_review' => true, 'crop_path' => 'archive/ocr-crops/crop.png',
    ]);

    $res = $this->actingAs(editorUser())->get("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$region->id}/crop")->assertOk();

    expect($res->headers->get('Content-Type'))->toBe('image/png')
        ->and($res->getContent())->toBe('fake-png-bytes');
});

it('404s a region crop that was never generated', function () {
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id]);
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => OcrRegionType::PrintedText->value,
        'bbox' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10], 'ocr_allowed' => true,
        'ai_correction_allowed' => true, 'requires_human_review' => false,
    ]);

    $this->actingAs(editorUser())->get("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$region->id}/crop")->assertNotFound();
});
