<?php

use App\Enums\OcrRegionType;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileOcrFormField;
use App\Models\FileOcrRegion;
use App\Models\OcrHandwritingSuggestion;
use App\Support\Ocr\HandwritingOcrException;
use App\Support\Ocr\HandwritingOcrProvider;
use App\Support\Ocr\HandwritingSuggestion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Records every crop it is asked to read. */
class RecordingHandwritingProvider implements HandwritingOcrProvider
{
    public int $calls = 0;

    public function __construct(
        public string $answer = 'أحمد المغلوث',
        public bool $external = false,
        public bool $fail = false,
    ) {}

    public function name(): string
    {
        return 'fake-hw';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function suggest(string $cropImagePath, ?string $language): HandwritingSuggestion
    {
        if ($this->fail) {
            throw new HandwritingOcrException('provider down', true);
        }
        $this->calls++;

        return new HandwritingSuggestion($this->answer, 0.42, 'fake-model@2026', ['engine' => 'fake', 'bytes' => filesize($cropImagePath)]);
    }
}

function handwritingProvider(RecordingHandwritingProvider $provider): RecordingHandwritingProvider
{
    app()->instance(HandwritingOcrProvider::class, $provider);

    return $provider;
}

/**
 * An archive item whose file has a handwritten form value (with a stored
 * crop) paired with a printed label, plus a signature.
 *
 * @return array{0: ArchiveItem, 1: File, 2: FileOcrRegion, 3: FileOcrFormField}
 */
function handwrittenForm(string $accessLevel = 'public', string $cropBytes = 'png-bytes-of-a-name'): array
{
    Storage::fake('local');
    $item = ArchiveItem::factory()->create(['access_level' => $accessLevel]);
    $file = File::factory()->create(['archive_item_id' => $item->id, 'disk' => 'local', 'ocr_status' => 'completed']);
    $region = hwRegion($file, OcrRegionType::Handwriting, $cropBytes);
    $label = hwRegion($file, OcrRegionType::FormLabel, null);
    $field = FileOcrFormField::create([
        'file_id' => $file->id, 'field_label' => 'اسم الفنان/ة', 'label_region_id' => $label->id, 'value_region_id' => $region->id,
        'value_type' => 'handwriting', 'requires_manual_transcription' => true,
    ]);

    return [$item, $file, $region, $field];
}

function hwRegion(File $file, OcrRegionType $type, ?string $cropBytes): FileOcrRegion
{
    $region = FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 2, 'region_type' => $type->value,
        'bbox' => ['x' => 1, 'y' => 1, 'width' => 10, 'height' => 10], 'ocr_allowed' => $type->ocrAllowed(),
        'ai_correction_allowed' => $type->aiCorrectionAllowed(), 'requires_human_review' => $type->requiresHumanReview(),
    ]);
    if ($cropBytes !== null) {
        $path = "archive/ocr-crops/file-{$file->id}/region-{$region->id}.png";
        Storage::disk('local')->put($path, $cropBytes);
        $region->update(['crop_path' => $path, 'crop_sha256' => hash('sha256', $cropBytes)]);
    }

    return $region;
}

function suggestUrl(ArchiveItem $item, FileOcrRegion $region): string
{
    return "/api/v1/archive-items/{$item->id}/file/ocr/regions/{$region->id}/handwriting-suggestion";
}

function reviewUrl(ArchiveItem $item, int $suggestionId): string
{
    return "/api/v1/archive-items/{$item->id}/file/ocr/handwriting-suggestions/{$suggestionId}/review";
}

beforeEach(function () {
    config()->set('ocr.handwriting.enabled', true);
    config()->set('ocr.handwriting.allow_external_providers', false);
    config()->set('ocr.handwriting.external_max_access_level', 'public');
});

it('stores a suggestion with its provenance, and leaves the field untranscribed and in review', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, $file, $region, $field] = handwrittenForm();
    $editor = editorUser();

    $this->actingAs($editor)->postJson(suggestUrl($item, $region))->assertOk()
        ->assertJsonPath('data.text', 'أحمد المغلوث')
        ->assertJsonPath('data.provider', 'fake-hw')
        ->assertJsonPath('data.decision', null);

    $suggestion = OcrHandwritingSuggestion::sole();
    expect($suggestion->model)->toBe('fake-model')
        ->and($suggestion->model_version)->toBe('fake-model@2026')
        ->and($suggestion->confidence)->toBe(0.42)
        ->and($suggestion->raw_result['engine'])->toBe('fake')
        ->and($suggestion->crop_path)->toBe($region->crop_path)
        ->and($suggestion->requested_by_user_id)->toBe($editor->id)
        // Never a verified value by itself.
        ->and($field->fresh()->manual_value)->toBeNull()
        ->and($field->fresh()->requires_manual_transcription)->toBeTrue()
        ->and($region->fresh()->requires_human_review)->toBeTrue();
});

it('never pays twice for the same crop, even after OCR is re-run and the region recreated', function () {
    $provider = handwritingProvider(new RecordingHandwritingProvider);
    [$item, $file, $region] = handwrittenForm();
    $editor = editorUser();

    $first = $this->actingAs($editor)->postJson(suggestUrl($item, $region))->json('data.id');
    $this->actingAs($editor)->postJson(suggestUrl($item, $region))->assertJsonPath('data.id', $first);

    // Re-OCR: same page, same crop bytes, new region row.
    $recreated = hwRegion($file, OcrRegionType::Handwriting, 'png-bytes-of-a-name');
    $this->actingAs($editor)->postJson(suggestUrl($item, $recreated))->assertJsonPath('data.id', $first);

    expect($provider->calls)->toBe(1);
});

it('never sends a signature, and refuses a region without a stored crop', function () {
    $provider = handwritingProvider(new RecordingHandwritingProvider);
    [$item, $file] = handwrittenForm();
    $signature = hwRegion($file, OcrRegionType::Signature, 'png-bytes-of-a-signature');
    $noCrop = hwRegion($file, OcrRegionType::Handwriting, null);

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $signature))->assertUnprocessable()->assertJsonPath('errors.region.0', 'region_type_not_eligible');
    $this->actingAs(editorUser())->postJson(suggestUrl($item, $noCrop))->assertUnprocessable()->assertJsonPath('errors.region.0', 'no_crop');

    expect($provider->calls)->toBe(0);
});

it('applies the privacy policy before any image leaves: off, no provider, external not allowed, or material too restricted', function (Closure $arrange, string $reason) {
    [$item, , $region] = handwrittenForm('institution_only');
    $provider = handwritingProvider($arrange() ?? new RecordingHandwritingProvider);

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertUnprocessable()->assertJsonPath('errors.region.0', $reason);
    expect($provider->calls ?? 0)->toBe(0);
})->with([
    'disabled' => [fn () => config()->set('ocr.handwriting.enabled', false), 'disabled'],
    'external not opted in' => [fn () => new RecordingHandwritingProvider(external: true), 'external_provider_not_allowed'],
    'external, material above the ceiling' => [function () {
        config()->set('ocr.handwriting.allow_external_providers', true);

        return new RecordingHandwritingProvider(external: true);
    }, 'access_level_not_allowed'],
]);

it('refuses outright when no provider is configured — manual transcription only', function () {
    [$item, , $region] = handwrittenForm();

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertUnprocessable()->assertJsonPath('errors.region.0', 'no_provider_configured');
});

it('sends a local provider any material, and an opted-in external one public material', function () {
    config()->set('ocr.handwriting.allow_external_providers', true);
    $external = handwritingProvider(new RecordingHandwritingProvider(external: true));
    [$item, , $region] = handwrittenForm('public');

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertOk();

    expect($external->calls)->toBe(1);
});

it('writes the transcription only when a reviewer accepts or edits, and records who decided', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, , $region, $field] = handwrittenForm();
    $reviewer = editorUser();
    $id = $this->actingAs($reviewer)->postJson(suggestUrl($item, $region))->json('data.id');

    $this->actingAs($reviewer)->postJson(reviewUrl($item, $id), ['decision' => 'edited', 'final_text' => 'أحمد عبدالله المغلوث'])->assertOk()
        ->assertJsonPath('data.decision', 'edited')
        ->assertJsonPath('data.final_text', 'أحمد عبدالله المغلوث')
        ->assertJsonPath('data.decided_by_user_id', $reviewer->id);

    expect($field->fresh())->manual_value->toBe('أحمد عبدالله المغلوث')->transcribed_by_user_id->toBe($reviewer->id);

    $this->actingAs($reviewer)->postJson(reviewUrl($item, $id), ['decision' => 'accepted'])->assertOk()->assertJsonPath('data.final_text', 'أحمد المغلوث');
    expect($field->fresh()->manual_value)->toBe('أحمد المغلوث');
});

it('leaves the field alone when a reviewer rejects the suggestion', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, , $region, $field] = handwrittenForm();
    $id = $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->json('data.id');

    $this->actingAs(editorUser())->postJson(reviewUrl($item, $id), ['decision' => 'rejected'])->assertOk()->assertJsonPath('data.decision', 'rejected');

    expect($field->fresh()->manual_value)->toBeNull();
});

it('refuses to accept a suggestion that read nothing, and an edit with no text', function () {
    handwritingProvider(new RecordingHandwritingProvider(answer: ''));
    [$item, , $region] = handwrittenForm();
    $id = $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertJsonPath('data.status', 'empty')->json('data.id');

    $this->actingAs(editorUser())->postJson(reviewUrl($item, $id), ['decision' => 'accepted'])->assertUnprocessable();
    $this->actingAs(editorUser())->postJson(reviewUrl($item, $id), ['decision' => 'edited', 'final_text' => '  '])->assertUnprocessable();
});

it('settles a pending suggestion when the reviewer types the transcription directly', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, , $region, $field] = handwrittenForm();
    $reviewer = editorUser();
    $this->actingAs($reviewer)->postJson(suggestUrl($item, $region))->assertOk();

    $this->actingAs($reviewer)->postJson("/api/v1/archive-items/{$item->id}/file/ocr/form-fields/{$field->id}/transcribe", ['value' => 'أحمد عبدالله المغلوث'])->assertOk();

    expect(OcrHandwritingSuggestion::sole())
        ->decision->toBe('edited')
        ->final_text->toBe('أحمد عبدالله المغلوث')
        ->decided_by_user_id->toBe($reviewer->id);
});

it('stores nothing when the provider fails, so asking again calls it again', function () {
    $provider = handwritingProvider(new RecordingHandwritingProvider(fail: true));
    [$item, , $region] = handwrittenForm();

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertStatus(503)->assertJsonPath('retryable', true);

    expect(OcrHandwritingSuggestion::count())->toBe(0);
});

it('rejects a region or suggestion belonging to another archive item', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, , $region] = handwrittenForm();
    $other = ArchiveItem::factory()->create();
    $id = $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->json('data.id');

    $this->actingAs(editorUser())->postJson(suggestUrl($other, $region))->assertNotFound();
    $this->actingAs(editorUser())->postJson(reviewUrl($other, $id), ['decision' => 'rejected'])->assertNotFound();
});

it('logs each provider call with what produced it, never the text', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    Log::spy();
    [$item, , $region] = handwrittenForm();

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertOk();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Handwriting suggestion provider call'
        && $context['provider'] === 'fake-hw' && $context['model_version'] === 'fake-model@2026'
        && ! str_contains(json_encode($context, JSON_UNESCAPED_UNICODE), 'المغلوث'))->once();
});

it('shows availability and each field\'s suggestion in the review bundle', function () {
    handwritingProvider(new RecordingHandwritingProvider);
    [$item, , $region] = handwrittenForm();
    $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")
        ->assertJsonPath('data.handwriting.available', true)
        ->assertJsonPath('data.handwriting.provider', 'fake-hw')
        ->assertJsonPath('data.form_fields.0.suggestion', null)
        ->assertJsonPath('data.form_fields.0.can_request_suggestion', true);

    $this->actingAs(editorUser())->postJson(suggestUrl($item, $region))->assertOk();

    $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")
        ->assertJsonPath('data.form_fields.0.suggestion.text', 'أحمد المغلوث');
});

it('reports manual-only when no provider is configured', function () {
    [$item] = handwrittenForm();

    $this->actingAs(editorUser())->getJson("/api/v1/archive-items/{$item->id}/file/ocr")
        ->assertJsonPath('data.handwriting', ['available' => false, 'reason' => 'no_provider_configured', 'provider' => null, 'external' => false]);
});

it('batches a file from the command line, with a dry run that sends nothing', function () {
    $provider = handwritingProvider(new RecordingHandwritingProvider);
    [, $file] = handwrittenForm();

    $this->artisan('ocr:suggest-handwriting', ['file' => $file->id, '--dry-run' => true])
        ->expectsTable(['', 'count'], [['eligible crops', 1], ['already suggested', 0], ['would call provider', 1], ['skipped: region_type_not_eligible', 1]])
        ->assertSuccessful();
    expect($provider->calls)->toBe(0);

    $this->artisan('ocr:suggest-handwriting', ['file' => $file->id])->assertSuccessful();
    $this->artisan('ocr:suggest-handwriting', ['file' => $file->id])->assertSuccessful();
    expect($provider->calls)->toBe(1);
});
