<?php

use App\Enums\DocumentType;
use App\Enums\ExtractedFieldStatus;
use App\Enums\OcrRegionType;
use App\Enums\OcrStage;
use App\Enums\ProposalStatus;
use App\Jobs\ExtractOcrFieldsJob;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileExtractedText;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Support\Facades\Queue;

/** A synthetic document: its page text, printed regions (one per string) and form fields, already through recognition. */
function scannedDocument(string $pageText, array $printed = [], array $formFields = [], array $fileAttributes = []): File
{
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'image/png', 'ocr_status' => 'completed', ...$fileAttributes]);
    FileExtractedText::create(['file_id' => $file->id, 'page_number' => 1, 'language' => 'ar', 'text' => $pageText, 'confidence' => 88]);

    foreach ($printed as $i => $text) {
        docRegion($file, OcrRegionType::PrintedText, $text, ['bbox' => ['x' => 100, 'y' => 100 + $i * 60, 'width' => 800, 'height' => 40]]);
    }
    foreach ($formFields as [$label, $manual]) {
        $value = docRegion($file, OcrRegionType::Handwriting, null, ['crop_path' => 'archive/ocr-crops/value.png']);
        FileOcrFormField::create([
            'file_id' => $file->id, 'field_label' => $label, 'value_region_id' => $value->id, 'value_type' => 'handwriting',
            'requires_manual_transcription' => true, 'manual_value' => $manual, 'transcribed_at' => $manual === null ? null : now(),
        ]);
    }

    return $file;
}

function docRegion(File $file, OcrRegionType $type, ?string $text, array $overrides = []): FileOcrRegion
{
    return FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => $type->value, 'language' => 'ar',
        'bbox' => ['x' => 100, 'y' => 900, 'width' => 300, 'height' => 40], 'confidence' => 86, 'source_text' => $text,
        'ocr_allowed' => $type->ocrAllowed(), 'ai_correction_allowed' => $type->aiCorrectionAllowed(),
        'requires_human_review' => $type->requiresHumanReview(), 'review_reason' => null, ...$overrides,
    ]);
}

function runExtraction(File $file): void
{
    (new ExtractOcrFieldsJob($file->id))->handle();
}

function schemaField(File $file, string $key, int $ordinal = 0): FileExtractedField
{
    return $file->extractedFields()->where('field_key', $key)->where('ordinal', $ordinal)->sole();
}

function fieldUrl(File $file, FileExtractedField $field, string $suffix = ''): string
{
    return "/api/v1/archive-items/{$file->archive_item_id}/file/ocr/fields/{$field->id}{$suffix}";
}

function conditionReport(): File
{
    return scannedDocument(
        "تقرير حالة عمل فني\nاسم الفنان: فنان المثال\nالعنوان: صورة زيتية\nالخامة: زيت على قماش\nالأبعاد: ٥٠×٧٠ سم\nالحالة: جيدة\nتاريخ التقرير: 14/05/2023",
        ["تقرير حالة عمل فني\nاسم الفنان: فنان المثال\nالعنوان: صورة زيتية\nالخامة: زيت على قماش\nالأبعاد: ٥٠×٧٠ سم\nالحالة: جيدة", 'تاريخ التقرير: 14/05/2023'],
    );
}

describe('the extract stage', function () {
    it("classifies the document and stores its type's fields and dates with where each came from", function () {
        $file = conditionReport();

        runExtraction($file);
        $file->refresh();

        $medium = schemaField($file, 'medium');
        expect($file->document_type)->toBe(DocumentType::ArtworkConditionReport)
            ->and($medium->extracted_value)->toBe('زيت على قماش')
            ->and($medium->document_type)->toBe(DocumentType::ArtworkConditionReport)
            ->and($medium->rule)->toBe('inline_label')
            ->and($medium->region_id)->toBe($file->ocrRegions()->orderBy('id')->first()->id)
            ->and($medium->original_ocr_text)->toBe('الخامة: زيت على قماش')
            ->and(schemaField($file, 'dimensions')->extracted_value)->toBe('٥٠×٧٠ سم')
            ->and(schemaField($file, 'artwork_title')->extracted_value)->toBe('صورة زيتية');

        $date = $file->extractedDates()->sole();
        expect($date->field_key)->toBe('report_date')
            ->and($date->normalized)->toBe('2023-05-14')
            ->and($date->region_id)->toBe($file->ocrRegions()->orderBy('id')->skip(1)->first()->id)
            ->and($date->confidence)->toBe(80)
            ->and($date->status)->toBe(ExtractedFieldStatus::Pending);
    });

    it('never reads text from a handwriting region, and gives an untranscribed value no value at all', function () {
        $file = scannedDocument('خطاب تفويض الفنانين أفوض الجمعية وحقوق الطبع والنشر', ['أنا الموقع أدناه أفوض الجمعية'], [['البريد الإلكتروني', null]]);
        // Even a handwriting row carrying text (it shouldn't) is never read as printed.
        docRegion($file, OcrRegionType::Handwriting, 'العنوان: not-to-be-trusted');

        runExtraction($file);

        $email = schemaField($file, 'email');
        expect($email->extracted_value)->toBeNull()
            ->and($email->crop_path)->toBe('archive/ocr-crops/value.png')
            ->and($email->form_field_id)->not->toBeNull()
            ->and($file->extractedFields()->where('field_key', 'address')->exists())->toBeFalse();
    });

    it('takes a transcription as the value once a reviewer types it — extraction re-runs by itself', function () {
        $file = scannedDocument('خطاب تفويض الفنانين أفوض الجمعية وحقوق الطبع والنشر', ['أنا الموقع أدناه أفوض الجمعية'], [['البريد الإلكتروني', null]]);
        app(OcrPipeline::class)->start($file, OcrStage::Extract);
        runQueuedOcrStages();
        $formField = $file->ocrFormFields()->sole();

        $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr/form-fields/{$formField->id}/transcribe", ['value' => 'artist@example.test'])->assertOk();
        runQueuedOcrStages();

        $email = schemaField($file, 'email');
        expect($email->extracted_value)->toBe('artist@example.test')
            ->and($email->extraction_method->value)->toBe('manually_transcribed')
            ->and($email->status)->toBe(ExtractedFieldStatus::Pending);
        Queue::assertPushed(ExtractOcrFieldsJob::class, 2);
    });

    it('keeps list items a reviewer decided, suggests only new ones, and numbers them after', function () {
        $file = scannedDocument('', ["المعارض\nمعرض الرياض 1972\nمعرض جدة 1975"], fileAttributes: ['document_type' => 'artist_biography', 'document_type_set_by_user_id' => editorUser()->id]);
        runExtraction($file);
        schemaField($file, 'exhibitions', 1)->update(['status' => ExtractedFieldStatus::Rejected->value]);

        $file->ocrRegions()->first()->update(['source_text' => "المعارض\nمعرض الرياض 1972\nمعرض جدة 1975\nمعرض الدمام 1980"]);
        runExtraction($file);

        $items = $file->extractedFields()->where('field_key', 'exhibitions')->orderBy('ordinal')->get();
        expect($items->pluck('extracted_value')->all())->toBe(['معرض الرياض 1972', 'معرض جدة 1975', 'معرض الدمام 1980'])
            ->and($items->pluck('status')->map->value->all())->toBe(['pending', 'rejected', 'pending'])
            ->and($items->pluck('ordinal')->all())->toBe([0, 1, 2]);
    });

    it("keeps a reviewer's document type over the classifier's", function () {
        $file = conditionReport();
        $file->update(['document_type' => 'artist_biography', 'document_type_set_by_user_id' => editorUser()->id]);

        runExtraction($file);

        expect($file->refresh()->document_type)->toBe(DocumentType::ArtistBiography)
            ->and($file->extractedFields()->where('field_key', 'medium')->exists())->toBeFalse()
            ->and($file->ocrStageRuns()->where('stage', 'extract')->sole()->summary['document_type_source'])->toBe('reviewer');
    });
});

describe('reviewing extracted fields', function () {
    it('verifies an artwork field without writing any record — it waits for the artwork to be confirmed', function () {
        $file = conditionReport();
        runExtraction($file);
        $medium = schemaField($file, 'medium');

        $this->actingAs(editorUser())->postJson(fieldUrl($file, $medium, '/accept'))->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.route', 'entity')
            ->assertJsonPath('data.target', 'artwork.medium')
            ->assertJsonPath('data.verified_value', 'زيت على قماش');

        expect(EditProposal::query()->exists())->toBeFalse();
    });

    it('refuses to accept contact details here: they go through the contact proposal', function () {
        $file = scannedDocument('خطاب تفويض الفنانين أفوض الجمعية وحقوق الطبع والنشر', [], [['رقم الجوال', '0500000001']]);
        runExtraction($file);
        Artist::factory()->create();

        $this->actingAs(editorUser())->postJson(fieldUrl($file, schemaField($file, 'phone'), '/accept'))->assertUnprocessable()->assertJsonValidationErrors('field_key');
        $this->actingAs(editorUser())->patchJson(fieldUrl($file, schemaField($file, 'phone')), ['extracted_value' => '0500000002'])->assertUnprocessable();

        expect(schemaField($file, 'phone')->status)->toBe(ExtractedFieldStatus::Pending);
    });

    it('refuses to accept a field with no value yet', function () {
        $file = scannedDocument('', [], [['الأبعاد', null]], ['document_type' => 'artwork_condition_report', 'document_type_set_by_user_id' => editorUser()->id]);
        runExtraction($file);

        $this->actingAs(editorUser())->postJson(fieldUrl($file, schemaField($file, 'dimensions'), '/accept'))->assertUnprocessable()->assertJsonValidationErrors('field');
    });

    it("applies the reviewer's edited wording to the item's draft, keeping the machine reading", function () {
        $editor = editorUser();
        $file = scannedDocument('Exhibition Opening 1979', ['Exhibition Opening 1979']);
        runExtraction($file);
        $title = $file->extractedFields()->where('field_key', 'title_ar')->sole();

        $this->actingAs($editor)->patchJson(fieldUrl($file, $title), ['extracted_value' => 'Exhibition opening, 1979'])->assertOk();
        $this->actingAs($editor)->postJson(fieldUrl($file, $title, '/accept'))->assertOk()
            ->assertJsonPath('data.extracted_value', 'Exhibition Opening 1979')
            ->assertJsonPath('data.verified_value', 'Exhibition opening, 1979');

        $draft = EditProposal::query()->where('citable_id', $file->archive_item_id)->where('status', ProposalStatus::Draft->value)->sole();
        expect($draft->payload['fields']['title']['ar'])->toBe('Exhibition opening, 1979');
    });

    it("bulk-accepts only the item's own fields, never document fields bound for other records", function () {
        $file = conditionReport();
        runExtraction($file);
        FileExtractedField::query()->where('file_id', $file->id)->update(['confidence' => 95]);

        $accepted = $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr/fields/accept-high-confidence")->assertOk()->json('data.accepted');

        expect($accepted)->toBe(['date_display', 'title_ar'])
            ->and(schemaField($file, 'medium')->status)->toBe(ExtractedFieldStatus::Pending);
    });

    it('confirms and rejects dates, only for the item they belong to', function () {
        $file = conditionReport();
        runExtraction($file);
        $date = $file->extractedDates()->sole();
        $other = conditionReport();

        $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr/dates/{$date->id}/accept")->assertOk()
            ->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.normalized', '2023-05-14')->assertJsonPath('data.field_key', 'report_date');
        $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$other->archive_item_id}/file/ocr/dates/{$date->id}/reject")->assertNotFound();

        // A confirmed date survives re-extraction and isn't suggested twice.
        runExtraction($file);
        expect($file->extractedDates()->count())->toBe(1)
            ->and(FileExtractedDate::query()->find($date->id)->status)->toBe(ExtractedFieldStatus::Accepted);
    });
});

describe('the document type', function () {
    it("lets a reviewer correct the classifier, re-extracting with the chosen type's fields, and hand it back", function () {
        $editor = editorUser();
        $file = conditionReport();
        app(OcrPipeline::class)->start($file, OcrStage::Extract);
        runQueuedOcrStages();

        $url = "/api/v1/archive-items/{$file->archive_item_id}/file/ocr/document-type";
        $this->actingAs($editor)->putJson($url, ['document_type' => 'artist_biography'])->assertOk()
            ->assertJsonPath('data.document_type_source', 'reviewer')->assertJsonPath('data.result', 'started');
        runQueuedOcrStages();
        expect($file->refresh()->document_type)->toBe(DocumentType::ArtistBiography)
            ->and($file->document_type_set_by_user_id)->toBe($editor->id)
            ->and($file->extractedFields()->where('field_key', 'medium')->exists())->toBeFalse();

        $this->actingAs($editor)->putJson($url, ['document_type' => null])->assertOk()->assertJsonPath('data.document_type_source', 'classifier');
        runQueuedOcrStages();
        expect($file->refresh()->document_type)->toBe(DocumentType::ArtworkConditionReport)
            ->and($file->extractedFields()->where('field_key', 'medium')->exists())->toBeTrue();

        $this->actingAs($editor)->putJson($url, ['document_type' => 'a_letter'])->assertUnprocessable();
    });

    it('shows the schema in the review bundle, with what was and was not found', function () {
        $file = conditionReport();
        runExtraction($file);

        $data = $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr")->assertOk()->json('data');
        $schema = collect($data['schema'])->keyBy('key');

        expect($data['document_type'])->toBe('artwork_condition_report')
            ->and($data['document_type_source'])->toBe('classifier')
            ->and($schema['medium'])->toMatchArray(['found' => true, 'route' => 'entity', 'target' => 'artwork.medium', 'label' => ['ar' => 'الخامة', 'en' => 'Medium']])
            ->and($schema['report_date']['found'])->toBeTrue()
            ->and($schema['conservation_notes']['found'])->toBeFalse()
            // The item's own fields come first, then the document's in schema order.
            ->and(array_column($data['fields'], 'field_key'))->toBe(['date_display', 'title_ar', 'artist', 'artwork_title', 'dimensions', 'medium', 'condition']);
    });
});
