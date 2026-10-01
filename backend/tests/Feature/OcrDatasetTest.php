<?php

use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedDate;
use App\Models\FileExtractedField;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Models\FileOcrRegionCorrection;
use App\Models\OcrCorrection;
use App\Models\OcrDatasetDocument;
use App\Models\OcrDatasetExport;
use App\Models\OcrEvaluationReservedFile;
use App\Models\OcrHandwritingSuggestion;
use App\Models\OcrReviewExample;
use App\Support\Ocr\Dataset\DatasetSplits;
use App\Support\Ocr\Evaluation\ExampleEvaluator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/**
 * Reviewer decisions kept as evaluation/training data: what is recorded, what
 * never is, which split it lands in, and what an export may contain.
 * Synthetic documents and values only.
 */

/**
 * @return array{0: ArchiveItem, 1: File}
 */
function datasetDocument(string $access = 'public', string $type = 'artist_biography', ?string $sha = null): array
{
    $item = ArchiveItem::factory()->create(['access_level' => $access]);
    $file = File::factory()->create(['archive_item_id' => $item->id, 'document_type' => $type, ...($sha !== null ? ['sha256' => $sha] : [])]);

    return [$item, $file];
}

function datasetField(File $file, string $key, ?string $value, array $overrides = []): FileExtractedField
{
    return FileExtractedField::create([
        'file_id' => $file->id, 'field_key' => $key, 'document_type' => 'artist_biography', 'extracted_value' => $value,
        'confidence' => 80, 'source_page' => 1, 'extraction_method' => 'ocr_derived', 'status' => 'pending', ...$overrides,
    ]);
}

function datasetFieldUrl(ArchiveItem $item, FileExtractedField $field, string $action): string
{
    return "/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}/{$action}";
}

/** A region with an AI correction that changes "مدينه" to "مدينة". */
function correctedRegion(File $file, string $line, array $changes, bool $needsReview = false): FileOcrRegion
{
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => 'printed_text', 'language' => 'ar',
        'bbox' => ['x' => 1, 'y' => 1, 'width' => 10, 'height' => 10], 'confidence' => 70, 'source_text' => $line,
        'ocr_allowed' => true, 'ai_correction_allowed' => true, 'requires_human_review' => false,
    ]);
    $correction = OcrCorrection::create([
        'input_hash' => hash('sha256', $line.json_encode($changes)), 'provider' => 'fake', 'model' => 'fake-model', 'prompt_version' => 'p3', 'rules_version' => 'r2',
        'input_chars' => mb_strlen($line), 'status' => OcrCorrection::STATUS_VALID, 'corrected_text' => $line, 'changes' => $changes,
        'name_candidates' => [], 'model_confidence' => 0.9, 'model_needs_review' => $needsReview,
    ]);
    FileOcrRegionCorrection::create([
        'file_id' => $file->id, 'region_id' => $region->id, 'ocr_correction_id' => $correction->id,
        'status' => $needsReview ? 'needs_review' : 'corrected', 'corrected_text' => $line, 'needs_review' => $needsReview,
    ]);

    return $region;
}

it('records an accepted value with what OCR read, what the AI suggested and what the reviewer kept', function () {
    [$item, $file] = datasetDocument();
    $region = correctedRegion($file, 'مكان الميلاد: مدينه الرياض', [['original' => 'مدينه', 'corrected' => 'مدينة', 'type' => 'orthographic_normalization']]);
    $field = datasetField($file, 'birth_place', 'مدينه الرياض', ['region_id' => $region->id]);
    $editor = editorUser();

    $this->actingAs($editor)->patchJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}", ['extracted_value' => 'مدينة الرياض'])->assertOk();
    $this->postJson(datasetFieldUrl($item, $field, 'accept'))->assertOk();

    $example = OcrReviewExample::sole();
    expect($example)->toMatchArray([
        'kind' => 'field', 'key' => 'birth_place', 'document_type' => 'artist_biography', 'language' => 'ar',
        'ocr_text' => 'مدينه الرياض', 'ai_correction' => 'مدينة الرياض', 'human_correction' => 'مدينة الرياض',
        'accepted' => true, 'reviewer_action' => 'corrected', 'extraction_method' => 'ocr_derived', 'reviewer_id' => $editor->id,
    ])
        ->and($example->ai_meta)->toMatchArray(['kind' => 'correction', 'changed' => true, 'model' => 'fake-model', 'prompt_version' => 'p3'])
        // The split is fixed when the first example is collected.
        ->and(OcrDatasetDocument::where('archive_item_id', $item->id)->value('split'))->toBeIn(['training', 'evaluation']);
});

it('records a rejection and an uncertain value without a correct answer', function () {
    [$item, $file] = datasetDocument();
    $rejected = datasetField($file, 'birth_place', 'جدة');
    $uncertain = datasetField($file, 'artist', 'سارة الراشد');
    $this->actingAs(editorUser());

    $this->postJson(datasetFieldUrl($item, $rejected, 'reject'))->assertOk();
    $this->postJson(datasetFieldUrl($item, $uncertain, 'uncertain'), ['note' => 'Faded'])->assertOk();

    expect(OcrReviewExample::where('subject_id', $rejected->id)->sole())->toMatchArray(['accepted' => false, 'reviewer_action' => 'rejected', 'human_correction' => null])
        ->and(OcrReviewExample::where('subject_id', $uncertain->id)->sole())->toMatchArray(['accepted' => null, 'reviewer_action' => 'uncertain', 'human_correction' => null]);
});

it('records accepted and rejected dates as written', function () {
    [$item, $file] = datasetDocument();
    $date = FileExtractedDate::create(['file_id' => $file->id, 'value' => '١٤٤٥/٠٨/٠٢', 'calendar' => 'hijri', 'date_type' => 'birth_date', 'source_page' => 1, 'source_method' => 'ocr', 'status' => 'pending']);

    $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$item->id}/file/ocr/dates/{$date->id}/accept")->assertOk();

    expect(OcrReviewExample::sole())->toMatchArray(['kind' => 'date', 'key' => 'birth_date', 'ocr_text' => '١٤٤٥/٠٨/٠٢', 'human_correction' => '١٤٤٥/٠٨/٠٢', 'accepted' => true]);
});

it('records a handwriting suggestion once — not again as the form field it fills', function () {
    [$item, $file] = datasetDocument('public', 'artwork_condition_report');
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => 'handwriting', 'bbox' => ['x' => 1, 'y' => 1, 'width' => 5, 'height' => 5],
        'crop_path' => 'ocr/c.png', 'crop_sha256' => str_repeat('b', 64), 'requires_human_review' => true,
    ]);
    FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'عنوان العمل', 'value_region_id' => $region->id, 'requires_manual_transcription' => true]);
    $suggestion = OcrHandwritingSuggestion::create([
        'file_id' => $file->id, 'region_id' => $region->id, 'crop_path' => 'ocr/c.png', 'crop_sha256' => str_repeat('b', 64),
        'provider' => 'kraken', 'model' => 'arabic-hw', 'status' => 'suggested', 'text' => 'حديقه الصباح', 'confidence' => 0.55,
    ]);

    $this->actingAs(editorUser())->postJson("/api/v1/archive-items/{$item->id}/file/ocr/handwriting-suggestions/{$suggestion->id}/review", ['decision' => 'edited', 'final_text' => 'حديقة الصباح'])->assertOk();

    expect(OcrReviewExample::sole())->toMatchArray([
        'kind' => 'handwriting', 'machine_suggestion' => 'حديقه الصباح', 'human_correction' => 'حديقة الصباح',
        'accepted' => false, 'reviewer_action' => 'edited', 'confidence' => 55,
    ]);
});

it('records a form field typed by hand, but never a contact detail, however it was read', function () {
    [$item, $file] = datasetDocument('public', 'artist_authorization');
    $name = FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'اسم الفنان', 'requires_manual_transcription' => true]);
    $phone = FileOcrFormField::create(['file_id' => $file->id, 'field_label' => 'رقم الجوال', 'requires_manual_transcription' => true]);
    $this->actingAs(editorUser());

    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/form-fields/{$name->id}/transcribe", ['value' => 'سارة الراشد'])->assertOk();
    $this->postJson("/api/v1/archive-items/{$item->id}/file/ocr/form-fields/{$phone->id}/transcribe", ['value' => '0500000001'])->assertOk();

    expect(OcrReviewExample::sole())->toMatchArray(['kind' => 'form_field', 'key' => 'artist_name', 'human_correction' => 'سارة الراشد', 'reviewer_action' => 'transcribed'])
        ->and(OcrReviewExample::query()->where('human_correction', 'like', '%0500000001%')->exists())->toBeFalse();
});

it('redacts contact-like text that turns up inside any other value', function () {
    [$item, $file] = datasetDocument();
    $field = datasetField($file, 'references', 'مقابلة، للتواصل someone@example.test أو 0500000001، عام 1985');

    $this->actingAs(editorUser())->postJson(datasetFieldUrl($item, $field, 'accept'))->assertOk();

    expect(OcrReviewExample::sole())->toMatchArray(['ocr_text' => 'مقابلة، للتواصل ⟦EMAIL⟧ أو ⟦NUMBER⟧، عام 1985', 'redacted' => true]);
});

it('records match decisions, and an undone one stops counting', function () {
    [$item, $file] = datasetDocument();
    $field = datasetField($file, 'artist', 'سارة الراشد');
    $match = FileEntityMatch::create([
        'file_id' => $file->id, 'extracted_field_id' => $field->id, 'entity_type' => 'artist', 'source_text' => 'سارة الراشد', 'status' => 'pending', 'matcher_version' => 'match-v1',
        'candidates' => [['id' => 41, 'key' => null, 'score' => 1, 'strength' => 'high', 'basis' => ['exact_name']], ['id' => 42, 'key' => null, 'score' => 0.5, 'strength' => 'medium', 'basis' => []]],
    ]);
    $this->actingAs(editorUser());

    $match->update(['status' => 'confirmed', 'confirmed_entity_id' => '42', 'reviewed_at' => now()]);
    expect(OcrReviewExample::where('kind', 'match')->latest('id')->first())->toMatchArray(['reviewer_action' => 'confirmed_other_candidate', 'accepted' => false])
        ->and(OcrReviewExample::where('kind', 'match')->latest('id')->first()->match)->toMatchArray(['top_id' => 41, 'top_strength' => 'high', 'confirmed_id' => '42']);

    $match->update(['status' => 'pending', 'confirmed_entity_id' => null]);
    expect(OcrReviewExample::latestDecisions()->where('kind', 'match')->count())->toBe(0);
});

it('assigns a split once, gives an identical file the same split, and reserves benchmark files for evaluation', function () {
    $splits = app(DatasetSplits::class);
    [$first] = datasetDocument('public', 'artist_biography', str_repeat('1', 64));
    [$copy] = datasetDocument('public', 'artist_biography', str_repeat('1', 64));
    [$benchmark] = datasetDocument('public', 'artist_authorization', str_repeat('2', 64));

    $assigned = $splits->assign($first->id);
    expect($splits->assign($first->id)->id)->toBe($assigned->id)
        ->and($splits->bucket($first->id))->toBe($splits->bucket($first->id))
        ->and($splits->assign($copy->id)->split)->toBe($assigned->split);

    $splits->reserveForEvaluation(str_repeat('2', 64), 'benchmark');
    expect($splits->assign($benchmark->id))->split->toBe('evaluation')->assigned_by->toBe('reserved')
        ->and(fn () => $splits->set($benchmark->id, 'training', null, null))->toThrow(ValidationException::class);
});

it('never moves an exported document between training and evaluation, though it can still be excluded', function () {
    [$item] = datasetDocument();
    $splits = app(DatasetSplits::class);
    $splits->set($item->id, 'training', null, 'Chosen by hand');
    OcrDatasetDocument::where('archive_item_id', $item->id)->update(['locked_at' => now()]);

    expect(fn () => $splits->set($item->id, 'evaluation', null, null))->toThrow(ValidationException::class)
        ->and($splits->set($item->id, 'excluded', null, 'Withdrawn')->split)->toBe('excluded');
});

it('exports one split as JSON Lines: the latest decisions, no people or record ids, only exportable material, and locks what it wrote', function () {
    [$public, $publicFile] = datasetDocument('public');
    [$internal, $internalFile] = datasetDocument('institution_only');
    $splits = app(DatasetSplits::class);
    $splits->set($public->id, 'training', null, null);
    $splits->set($internal->id, 'training', null, null);
    $field = datasetField($publicFile, 'birth_place', 'الرياض');
    $hidden = datasetField($internalFile, 'birth_place', 'جدة');
    $this->actingAs(editorUser());
    $this->postJson(datasetFieldUrl($public, $field, 'uncertain'))->assertOk();
    $this->postJson(datasetFieldUrl($public, $field, 'accept'))->assertOk();
    $this->postJson(datasetFieldUrl($internal, $hidden, 'accept'))->assertOk();
    $path = storage_path('framework/testing/dataset-'.uniqid().'.jsonl');

    Artisan::call('ocr:dataset:export', ['split' => 'training', '--output' => $path]);

    $lines = array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($path))));
    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toMatchArray([
            'split' => 'training', 'document_type' => 'artist_biography', 'language' => 'ar', 'kind' => 'field',
            'ocr_text' => 'الرياض', 'ai_correction' => null, 'human_correction' => 'الرياض', 'accepted' => true, 'reviewer_action' => 'accepted',
        ])
        ->and(array_keys($lines[0]))->not->toContain('reviewer_id', 'archive_item_id', 'file_id')
        ->and($lines[0]['document'])->toHaveLength(16)
        ->and(OcrDatasetDocument::where('archive_item_id', $public->id)->value('locked_at'))->not->toBeNull()
        ->and(OcrDatasetDocument::where('archive_item_id', $internal->id)->value('locked_at'))->toBeNull()
        ->and(OcrDatasetExport::sole())->toMatchArray(['split' => 'training', 'example_count' => 1, 'document_count' => 1, 'sha256' => hash_file('sha256', $path)]);
    @unlink($path);
});

it('holds back a training document whose file is evaluation data elsewhere', function () {
    [$training, $trainingFile] = datasetDocument('public', 'artist_biography', str_repeat('3', 64));
    OcrEvaluationReservedFile::create(['sha256' => str_repeat('3', 64), 'source' => 'benchmark']);
    OcrDatasetDocument::create(['archive_item_id' => $training->id, 'split' => 'training', 'assigned_by' => 'reviewer']);
    $field = datasetField($trainingFile, 'birth_place', 'الرياض');
    $this->actingAs(editorUser())->postJson(datasetFieldUrl($training, $field, 'accept'))->assertOk();
    $path = storage_path('framework/testing/dataset-'.uniqid().'.jsonl');

    Artisan::call('ocr:dataset:export', ['split' => 'training', '--output' => $path]);

    expect(trim((string) file_get_contents($path)))->toBe('')
        ->and(Artisan::output())->toContain('held back');
    @unlink($path);
});

it('measures each stage — above all, how often the AI correction made a value wrong', function () {
    $example = fn (array $a) => new OcrReviewExample(['kind' => 'field', 'reviewer_action' => 'accepted', 'accepted' => true, ...$a]);
    $ai = fn (bool $changed, bool $needsReview = false) => ['kind' => 'correction', 'changed' => $changed, 'needs_review' => $needsReview, 'status' => $needsReview ? 'needs_review' : 'corrected', 'provider' => 'fake', 'model' => 'm', 'prompt_version' => 'p3'];

    $metrics = app(ExampleEvaluator::class)->evaluate(collect([
        // The AI fixed a misreading.
        $example(['ocr_text' => 'مدينه', 'ai_correction' => 'مدينة', 'human_correction' => 'مدينة', 'reviewer_action' => 'corrected', 'ai_meta' => $ai(true)]),
        // The AI "fixed" a correct historical spelling: the harmful case.
        $example(['ocr_text' => 'الاحساء', 'ai_correction' => 'الأحساء', 'human_correction' => 'الاحساء', 'ai_meta' => $ai(true)]),
        // The AI left a misreading alone, and flagged the line for a person.
        $example(['ocr_text' => 'جده', 'ai_correction' => 'جده', 'human_correction' => 'جدة', 'reviewer_action' => 'corrected', 'ai_meta' => $ai(false, true)]),
        // No AI ran; a value a reviewer had to type in.
        $example(['ocr_text' => null, 'human_correction' => 'الرياض', 'reviewer_action' => 'transcribed', 'extraction_method' => 'manually_transcribed']),
        $example(['ocr_text' => 'خطأ', 'human_correction' => null, 'accepted' => false, 'reviewer_action' => 'rejected']),
        new OcrReviewExample(['kind' => 'match', 'reviewer_action' => 'confirmed_top', 'match' => ['top_id' => 1, 'top_strength' => 'high']]),
        new OcrReviewExample(['kind' => 'match', 'reviewer_action' => 'no_match', 'match' => ['top_id' => 2, 'top_strength' => 'high']]),
    ]));

    expect($metrics['correction'])->toMatchArray([
        'values' => 3, 'proposed' => 2, 'correction_accuracy' => 0.5, 'false_correction_rate' => 0.5,
        'harmful_correction_rate' => 0.3333, 'abstention_rate' => 0.3333, 'missed_rate' => 0.3333,
    ])
        ->and($metrics['ocr'])->toMatchArray(['values' => 3, 'exact_rate' => 0.3333])
        ->and($metrics['extraction'])->toMatchArray(['decided' => 4, 'field_precision' => 0.75, 'added_by_hand' => 1, 'observed_recall' => 0.75])
        ->and($metrics['matching'])->toMatchArray(['decided' => 2, 'correct_match_rate' => 0.5, 'false_match_rate' => 0.5])
        ->and($metrics['workflow'])->toMatchArray(['decisions' => 5, 'acceptance_rate' => 0.2, 'correction_rate' => 0.4, 'rejection_rate' => 0.2, 'manual_transcription_rate' => 0.2]);
});

it('prints the metrics for a split without any document text', function () {
    [$item, $file] = datasetDocument();
    OcrDatasetDocument::create(['archive_item_id' => $item->id, 'split' => 'evaluation', 'assigned_by' => 'reviewer']);
    $field = datasetField($file, 'birth_place', 'مدينه الرياض');
    $this->actingAs(editorUser())->patchJson("/api/v1/archive-items/{$item->id}/file/ocr/fields/{$field->id}", ['extracted_value' => 'مدينة الرياض'])->assertOk();
    $this->postJson(datasetFieldUrl($item, $field, 'accept'))->assertOk();

    expect(Artisan::call('ocr:evaluate', ['--json' => true]))->toBe(0);
    $output = Artisan::output();

    expect(json_decode($output, true)['ocr'])->toMatchArray(['values' => 1, 'cer' => 0.0833])
        ->and($output)->not->toContain('الرياض');
});
