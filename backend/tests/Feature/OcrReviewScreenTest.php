<?php

use App\Enums\OcrRegionType;
use App\Jobs\ExtractOcrFieldsJob;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedField;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Models\FileOcrRegionCorrection;
use App\Models\Holder;
use App\Models\OcrCorrection;
use App\Models\User;
use App\Support\Ocr\PdfPageRasterizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * What the reviewer sees beside each value (source region, its OCR, the AI
 * correction applied to just that value, what the record holds now), the
 * lines set aside for a person, and the actions the review screen offers.
 * Synthetic documents only.
 */

/** Renders two blank pages and counts how often it was asked. */
class ReviewScreenRasterizer implements PdfPageRasterizer
{
    public int $calls = 0;

    public function rasterize(string $pdfPath, string $outputDir): array
    {
        $this->calls++;
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        $paths = [];
        foreach ([1, 2] as $page) {
            $paths[] = $path = "{$outputDir}/page-{$page}.png";
            file_put_contents($path, "png-page-{$page}");
        }

        return $paths;
    }
}

/**
 * @return array{0: ArchiveItem, 1: File}
 */
function reviewDocument(string $documentType = 'artist_biography', array $item = []): array
{
    $archiveItem = ArchiveItem::factory()->create($item);
    $file = File::factory()->create(['archive_item_id' => $archiveItem->id, 'document_type' => $documentType]);

    return [$archiveItem, $file];
}

function reviewRegion(File $file, OcrRegionType $type, ?string $text, array $overrides = []): FileOcrRegion
{
    return FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => $type->value, 'language' => 'ar',
        'bbox' => ['x' => 100, 'y' => 200, 'width' => 400, 'height' => 40], 'confidence' => 82, 'source_text' => $text,
        'ocr_allowed' => $type->ocrAllowed(), 'ai_correction_allowed' => $type->aiCorrectionAllowed(),
        'requires_human_review' => $type->requiresHumanReview(), 'review_reason' => null, ...$overrides,
    ]);
}

function reviewField(File $file, string $key, ?string $value, array $overrides = []): FileExtractedField
{
    return FileExtractedField::create([
        'file_id' => $file->id, 'field_key' => $key, 'extracted_value' => $value, 'confidence' => 80, 'source_page' => 1,
        'extraction_method' => 'ocr_derived', 'status' => 'pending', ...$overrides,
    ]);
}

function reviewBundle(ArchiveItem $item): array
{
    return test()->getJson("/api/v1/archive-items/{$item->id}/file/ocr")->assertOk()->json('data');
}

function reviewFieldIn(array $bundle, int $id): array
{
    return collect($bundle['fields'])->firstWhere('id', $id);
}

it('shows each value beside its source region, that region\'s OCR, and the AI correction applied to just that value', function () {
    [$item, $file] = reviewDocument();
    $region = reviewRegion($file, OcrRegionType::PrintedText, 'مكان الميلاد: مدينه الرياض ⟦N1⟧');
    $field = reviewField($file, 'birth_place', 'مدينه الرياض', ['document_type' => 'artist_biography', 'region_id' => $region->id, 'original_ocr_text' => 'مكان الميلاد: مدينه الرياض']);
    $correction = OcrCorrection::create([
        'input_hash' => str_repeat('c', 64), 'provider' => 'fake', 'model' => 'fake-model', 'model_version' => '2026-01', 'prompt_version' => 'p3', 'rules_version' => 'r2',
        'input_chars' => 40, 'status' => OcrCorrection::STATUS_VALID, 'corrected_text' => 'مكان الميلاد: مدينة الرياض ⟦N1⟧',
        'changes' => [
            ['original' => 'مدينه', 'corrected' => 'مدينة', 'type' => 'orthographic_normalization'],
            ['original' => 'الميلاد', 'corrected' => 'الولادة', 'type' => 'spelling'],
            ['original' => '⟦N1⟧', 'corrected' => '١٩٤٥', 'type' => 'digits'],
        ],
        'name_candidates' => [['ocr_text' => 'الرياض', 'candidate' => 'الرياض', 'kind' => 'place']],
        'model_confidence' => 0.91, 'model_needs_review' => false,
    ]);
    FileOcrRegionCorrection::create([
        'file_id' => $file->id, 'region_id' => $region->id, 'ocr_correction_id' => $correction->id,
        'status' => 'corrected', 'corrected_text' => 'مكان الميلاد: مدينة الرياض ⟦N1⟧', 'needs_review' => false,
    ]);

    $this->actingAs(editorUser());
    $shown = reviewFieldIn(reviewBundle($item), $field->id);

    expect($shown['source_region'])->toMatchArray(['id' => $region->id, 'page_number' => 1, 'ocr_text' => 'مكان الميلاد: مدينه الرياض ⟦N1⟧', 'has_correction_mark' => false])
        // Only the change inside the value applies; the label's change and the masked span don't.
        ->and($shown['ai_correction'])->toMatchArray([
            'status' => 'corrected', 'suggested_value' => 'مدينة الرياض', 'model' => 'fake-model', 'prompt_version' => 'p3',
            'changes' => [['original' => 'مدينه', 'corrected' => 'مدينة', 'type' => 'orthographic_normalization']],
        ])
        ->and($shown['ai_correction']['name_candidates'])->toHaveCount(1)
        // A suggestion, never applied: the machine value stands until a person decides.
        ->and($shown['extracted_value'])->toBe('مدينه الرياض')
        ->and($shown['verified_value'])->toBeNull()
        ->and($shown['current_record_value'])->toBeNull();
});

it('shows what the record holds now for the archive item\'s own fields', function () {
    [$item, $file] = reviewDocument('unknown', ['source_name' => 'Ministry archive']);
    $field = reviewField($file, 'source_name', 'Ministry of Culture archive');

    $this->actingAs(editorUser());

    expect(reviewFieldIn(reviewBundle($item), $field->id))->toMatchArray(['current_record_value' => 'Ministry archive', 'source_region' => null, 'ai_correction' => null]);
});

it('lists the lines set aside for a person that nothing else shows', function () {
    [$item, $file] = reviewDocument();
    $struck = reviewRegion($file, OcrRegionType::PrintedText, 'افتتاح المعرض ١٤ مارس', ['requires_human_review' => true, 'has_correction_mark' => true, 'crop_path' => 'ocr/x.png']);
    $loose = reviewRegion($file, OcrRegionType::Handwriting, null, ['page_number' => 2]);
    reviewRegion($file, OcrRegionType::Signature, null);
    $paired = reviewRegion($file, OcrRegionType::Handwriting, null);
    FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'الاسم', 'value_region_id' => $paired->id, 'requires_manual_transcription' => true]);
    reviewRegion($file, OcrRegionType::PrintedText, 'سطر مقروء', ['requires_human_review' => false]);

    $this->actingAs(editorUser());
    $setAside = collect(reviewBundle($item)['set_aside_regions']);

    expect($setAside->pluck('id')->all())->toBe([$struck->id, $loose->id])
        ->and($setAside[0])->toMatchArray(['region_type' => 'printed_text', 'has_correction_mark' => true, 'has_crop' => true, 'ocr_text' => 'افتتاح المعرض ١٤ مارس', 'dismissed' => false])
        ->and($setAside[1])->toMatchArray(['region_type' => 'handwriting', 'page_number' => 2, 'ocr_text' => null]);
});

it('lets the reviewer type what a set-aside line says into a field of their choosing, keeping the line\'s own reading', function () {
    [$item, $file] = reviewDocument();
    $struck = reviewRegion($file, OcrRegionType::PrintedText, 'الاقتناءات: متحف الفن', ['requires_human_review' => true, 'has_correction_mark' => true, 'page_number' => 2]);
    $editor = editorUser();

    $res = $this->actingAs($editor)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$struck->id}/transcribe", [
        'field_key' => 'collections', 'value' => 'متحف الفنون الحديثة',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'edited')
        ->assertJsonPath('data.extracted_value', null)
        ->assertJsonPath('data.verified_value', 'متحف الفنون الحديثة')
        ->assertJsonPath('data.original_ocr_text', 'الاقتناءات: متحف الفن')
        ->assertJsonPath('data.extraction_method', 'manually_transcribed')
        ->assertJsonPath('data.source_page', 2)
        ->assertJsonPath('data.region_id', $struck->id);

    // A list field takes more than one; the line now shows what was typed from it.
    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$struck->id}/transcribe", ['field_key' => 'collections', 'value' => 'مجموعة خاصة'])
        ->assertCreated()->assertJsonPath('data.ordinal', 1);
    expect(collect(reviewBundle($item)['set_aside_regions'])->firstWhere('id', $struck->id)['transcribed_field_ids'])->toHaveCount(2);

    // It is a reviewer's value like any other: kept when extraction runs again, and accepted explicitly.
    (new ExtractOcrFieldsJob($file->id))->handle();
    expect(FileExtractedField::query()->whereKey($res->json('data.id'))->value('verified_value'))->toBe('متحف الفنون الحديثة');
    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$res->json('data.id')}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
});

it('refuses to transcribe a set-aside line into a contact, a date, an unknown field, or a single field that already has a value', function () {
    [$item, $file] = reviewDocument('artist_authorization');
    $line = reviewRegion($file, OcrRegionType::Handwriting, null);
    $url = "/api/v1/archive-items/{$item->id}/file/ocr/regions/{$line->id}/transcribe";
    $this->actingAs(editorUser());

    $this->postJson($url, ['field_key' => 'phone', 'value' => '0500000001'])->assertUnprocessable()->assertJsonValidationErrors('field_key');
    $this->postJson($url, ['field_key' => 'document_issue_date', 'value' => '١٤٤٥/٠٣/٠١'])->assertUnprocessable()->assertJsonValidationErrors('field_key');
    $this->postJson($url, ['field_key' => 'not_a_field', 'value' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('field_key');

    reviewField($file, 'title_ar', 'خطاب تفويض');
    $this->postJson($url, ['field_key' => 'title_ar', 'value' => 'خطاب'])->assertUnprocessable()->assertJsonValidationErrors('field_key');

    // A line nobody set aside isn't transcribed from here.
    $read = reviewRegion($file, OcrRegionType::PrintedText, 'مقروء', ['requires_human_review' => false]);
    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$read->id}/transcribe", ['field_key' => 'title_ar', 'value' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('region');
    expect(FileExtractedField::count())->toBe(1);
});

it('dismisses a set-aside line, and can undo it', function () {
    [$item, $file] = reviewDocument();
    $line = reviewRegion($file, OcrRegionType::Handwriting, null);
    $this->actingAs(editorUser());

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$line->id}/dismiss")->assertOk();
    expect(reviewBundle($item)['set_aside_regions'][0]['dismissed'])->toBeTrue();

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/regions/{$line->id}/restore")->assertOk();
    expect(reviewBundle($item)['set_aside_regions'][0]['dismissed'])->toBeFalse();
});

it('marks a value uncertain with a note: never applied, skipped by bulk accept, still open to a decision', function () {
    [$item, $file] = reviewDocument('unknown');
    $field = reviewField($file, 'source_name', 'Ministry archive', ['confidence' => 97]);
    $this->actingAs(editorUser());

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/uncertain", ['note' => 'Second word is smudged.'])->assertOk()
        ->assertJsonPath('data.status', 'uncertain')
        ->assertJsonPath('data.review_note', 'Second word is smudged.');

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/accept-high-confidence")->assertOk()->assertJsonPath('data.accepted', []);
    expect($item->refresh()->source_name)->not->toBe('Ministry archive');

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
});

it('keeps an uncertain field through re-extraction', function () {
    [$item, $file] = reviewDocument('unknown');
    $field = reviewField($file, 'source_name', 'Ministry archive');
    $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/uncertain")->assertOk();

    (new ExtractOcrFieldsJob($file->id))->handle();

    expect($field->refresh()->status->value)->toBe('uncertain');
});

it('never resets the record\'s date years or calendar when a date\'s display text is accepted', function () {
    [$item, $file] = reviewDocument('unknown');
    $item->update(['content_date_display' => '1395', 'content_year_from' => 1395, 'content_year_to' => 1396, 'content_calendar' => 'hijri', 'content_certainty' => 'range']);
    $field = reviewField($file, 'date_display', '١٣٩٥ هـ');
    seedRoles();
    // Without proposals.submit the accepted value goes straight onto the record.
    $user = tap(User::factory()->create())->givePermissionTo('archive.manage');

    $this->actingAs($user)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertOk();

    expect($item->refresh())
        ->content_date_display->toBe('١٣٩٥ هـ')
        ->content_year_from->toBe(1395)
        ->content_year_to->toBe(1396);
    expect($item->getRawOriginal('content_calendar'))->toBe('hijri');
});

it('keeps the date group when accepting into the reviewer\'s own draft', function () {
    [$item, $file] = reviewDocument('unknown');
    $item->update(['content_date_display' => '1975', 'content_year_from' => 1975, 'content_year_to' => 1975, 'content_calendar' => 'gregorian', 'content_certainty' => 'exact']);
    $field = reviewField($file, 'date_display', 'circa 1975');

    $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/accept")->assertOk();

    expect(EditProposal::sole()->payload['fields']['content'])->toMatchArray(['display' => 'circa 1975', 'year_from' => 1975, 'year_to' => 1975, 'calendar' => 'gregorian', 'certainty' => 'exact']);
});

it('serves a PDF page as OCR rendered it, rendering once and keeping it', function () {
    Storage::fake('local');
    $rasterizer = new ReviewScreenRasterizer;
    app()->instance(PdfPageRasterizer::class, $rasterizer);
    $item = ArchiveItem::factory()->create();
    $file = File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'application/pdf', 'path' => 'archive/letter.pdf']);
    Storage::disk('local')->put('archive/letter.pdf', '%PDF-1.4');
    $this->actingAs(editorUser());

    $page2 = $this->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/2/image")->assertOk();
    expect(file_get_contents($page2->baseResponse->getFile()->getPathname()))->toBe('png-page-2');
    $this->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/1/image")->assertOk();
    $this->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/3/image")->assertNotFound();

    // The third request re-tried the render for a page that doesn't exist; the kept pages were never re-rendered.
    expect($rasterizer->calls)->toBe(2)
        ->and(Storage::disk('local')->exists("archive/ocr-pages/file-{$file->id}/page-1.png"))->toBeTrue();
});

it('serves an image file as its own single page, only to those who may manage archive material', function () {
    Storage::fake('local');
    $item = ArchiveItem::factory()->create();
    File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'image/png', 'path' => 'archive/scan.png']);
    Storage::disk('local')->put('archive/scan.png', 'png-bytes');
    // Roles exist before the first permission check, which caches what it finds.
    $editor = editorUser();

    $this->actingAs(User::factory()->create())->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/1/image")->assertForbidden();

    Auth::forgetGuards();
    $this->actingAs($editor);
    $this->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/1/image")->assertOk();
    $this->get("/api/v1/archive-items/{$item->id}/file/ocr/pages/2/image")->assertNotFound();
});

it('lets the reviewer search for the record a name means when it is not among the candidates', function () {
    [$item, $file] = reviewDocument();
    $field = reviewField($file, 'artist', 'ص. الراشد', ['document_type' => 'artist_biography']);
    $match = FileEntityMatch::create(['file_id' => $file->id, 'extracted_field_id' => $field->id, 'entity_type' => 'artist', 'source_text' => 'ص. الراشد', 'candidates' => [], 'status' => 'pending', 'matcher_version' => 'match-v1']);
    $artist = Artist::factory()->create(['name_ar' => 'صالحة الراشد', 'name_en' => 'Saleha Alrashed']);
    $artist->variants()->create(['name' => 'Salha Al-Rashed', 'language' => 'en', 'type' => 'alternate']);
    Artist::factory()->create(['name_ar' => 'منى السالم', 'name_en' => 'Mona Alsalem']);
    $url = "/api/v1/archive-items/{$item->id}/file/ocr/matches/{$match->id}/search";
    $this->actingAs(editorUser());

    $this->getJson("{$url}?q=".urlencode('الراشد'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $artist->id)->assertJsonPath('data.0.label.en', 'Saleha Alrashed');
    $this->getJson("{$url}?q=Salha")->assertOk()->assertJsonPath('data.0.id', $artist->id);
    $this->getJson("{$url}?q=a")->assertOk()->assertJsonCount(0, 'data');

    // Linking is the confirm endpoint, and confirms the reading.
    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/matches/{$match->id}/confirm", ['entity_id' => $artist->id])->assertOk()
        ->assertJsonPath('data.status', 'confirmed');
});

it('never finds a private holder by its real name for someone who may not see that name', function () {
    [$item, $file] = reviewDocument();
    $field = reviewField($file, 'collections', 'مجموعة آل سامي', ['document_type' => 'artist_biography']);
    $match = FileEntityMatch::create(['file_id' => $file->id, 'extracted_field_id' => $field->id, 'entity_type' => 'holder', 'source_text' => 'مجموعة آل سامي', 'candidates' => [], 'status' => 'pending', 'matcher_version' => 'match-v1']);
    $private = Holder::factory()->create(['name_ar' => 'مجموعة آل سامي', 'name_en' => 'Al Sami Collection', 'is_public_name' => false, 'city_en' => 'Jeddah', 'city_ar' => 'جدة']);
    $url = "/api/v1/archive-items/{$item->id}/file/ocr/matches/{$match->id}/search?q=".urlencode('آل سامي');

    seedRoles();
    $this->actingAs(tap(User::factory()->create())->givePermissionTo('archive.manage'))->getJson($url)->assertOk()->assertJsonCount(0, 'data');

    Auth::forgetGuards();
    $this->actingAs(editorUser())->getJson($url)->assertOk()->assertJsonPath('data.0.id', $private->id)->assertJsonPath('data.0.label.en', 'Al Sami Collection');
});
