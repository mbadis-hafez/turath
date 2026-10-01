<?php

use App\Enums\OcrRegionType;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileOcrRegion;
use App\Models\FileOcrRegionCorrection;
use App\Models\OcrCorrection;
use App\Support\Ocr\Correction\NullCorrectionProvider;
use App\Support\Ocr\Correction\OcrCorrectionException;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\Correction\OcrCorrectionService;
use App\Support\Ocr\Correction\ProviderRequest;
use App\Support\Ocr\Correction\ProviderResponse;
use Illuminate\Support\Facades\Log;

/** Records every request; answers with $answer (or echoes the text back unchanged). */
class RecordingCorrectionProvider implements OcrCorrectionProvider
{
    /** @var array<int, ProviderRequest> */
    public array $requests = [];

    /** @param  (Closure(string): (array|null))|null  $answer  masked text => payload */
    public function __construct(
        public ?Closure $answer = null,
        public string $providerName = 'fake',
        public string $modelName = 'fake-model',
        public bool $external = false,
        public ?Closure $failWhen = null,
    ) {}

    public function name(): string
    {
        return $this->providerName;
    }

    public function model(): string
    {
        return $this->modelName;
    }

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function correct(ProviderRequest $request): ProviderResponse
    {
        $text = sentText($request);
        if ($this->failWhen !== null && ($this->failWhen)($text)) {
            throw new OcrCorrectionException('fake provider is down', true);
        }
        $this->requests[] = $request;
        $payload = $this->answer !== null ? ($this->answer)($text) : unchangedAnswer($text);

        return new ProviderResponse($payload, ['echo' => $payload], $this->modelName.'-2026', 100, 20, $payload === null ? 'unparseable' : null);
    }
}

function sentText(ProviderRequest $request): string
{
    preg_match('~<ocr_text>\n(.*)\n</ocr_text>~su', $request->user, $m);

    return $m[1];
}

function unchangedAnswer(string $text, array $overrides = []): array
{
    return ['corrected_text' => $text, 'changes' => [], 'name_candidates' => [], 'confidence' => 0.95, 'needs_review' => false, 'reason' => null, ...$overrides];
}

function correctionFile(string $accessLevel = 'public'): File
{
    $item = ArchiveItem::factory()->create(['access_level' => $accessLevel]);

    return File::factory()->create(['archive_item_id' => $item->id, 'ocr_status' => 'completed']);
}

function ocrRegion(File $file, OcrRegionType $type, ?string $text, array $overrides = []): FileOcrRegion
{
    return FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => $type->value, 'language' => 'ar',
        'bbox' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10], 'confidence' => 80,
        'source_text' => $text, 'ocr_allowed' => $type->ocrAllowed(), 'ai_correction_allowed' => $type->aiCorrectionAllowed(),
        'requires_human_review' => $type->requiresHumanReview(), 'review_reason' => null,
        ...$overrides,
    ]);
}

function runCorrection(File $file, RecordingCorrectionProvider $provider, bool $dryRun = false): array
{
    return (new OcrCorrectionService($provider))->correctFile($file, $dryRun);
}

beforeEach(function () {
    config()->set('ocr.correction.enabled', true);
    config()->set('ocr.correction.allow_external_providers', false);
    config()->set('ocr.correction.external_max_access_level', 'public');
    config()->set('ocr.correction.review_below_confidence', 0.85);
});

it('sends only printed text, labels and footers — never handwriting, signatures, images, noise or unclassified regions', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص مطبوع');
    ocrRegion($file, OcrRegionType::FormLabel, 'الموضوع:');
    ocrRegion($file, OcrRegionType::Footer, 'هيئة الفنون البصرية');
    foreach ([OcrRegionType::Handwriting, OcrRegionType::Signature, OcrRegionType::Logo, OcrRegionType::Photograph, OcrRegionType::Noise, OcrRegionType::Unknown, OcrRegionType::FormValue] as $type) {
        ocrRegion($file, $type, "SECRET {$type->value}");
    }
    // Even a row whose flags wrongly allow correction is refused by type.
    ocrRegion($file, OcrRegionType::Handwriting, 'SECRET mis-flagged handwriting', ['ocr_allowed' => true, 'ai_correction_allowed' => true]);

    $provider = new RecordingCorrectionProvider;
    $summary = runCorrection($file, $provider);

    expect($provider->requests)->toHaveCount(3)
        ->and(collect($provider->requests)->map(fn ($r) => sentText($r))->implode('|'))->not->toContain('SECRET')
        ->and($summary['eligible'])->toBe(3)
        ->and($summary['skipped'])->toBe(['region_type_not_eligible' => 8]);
});

it('never sends contact details: emails, links and numbers are masked, then restored only in the region layer', function () {
    $file = correctionFile();
    $footer = ocrRegion($file, OcrRegionType::Footer, 'للتواصل info@gallery.example أو +966500000000 مؤسسه الفنون');

    $provider = new RecordingCorrectionProvider(fn (string $text) => unchangedAnswer(str_replace('مؤسسه', 'مؤسسة', $text), [
        'changes' => [['original' => 'مؤسسه', 'corrected' => 'مؤسسة', 'type' => 'orthographic_normalization']],
    ]));
    runCorrection($file, $provider);

    $sent = sentText($provider->requests[0]);
    expect($sent)->toBe('للتواصل ⟦E1⟧ أو ⟦N1⟧ مؤسسه الفنون')
        ->and(json_encode(OcrCorrection::sole()->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain('info@gallery.example')
        ->not->toContain('500000000');

    $layer = $footer->corrections()->sole();
    expect($layer->corrected_text)->toBe('للتواصل info@gallery.example أو +966500000000 مؤسسة الفنون')
        ->and($layer->status)->toBe('corrected')
        ->and($layer->needs_review)->toBeFalse();
});

it('keeps the OCR text untouched and records the correction as a separate layer with its provenance', function () {
    $file = correctionFile();
    $region = ocrRegion($file, OcrRegionType::PrintedText, 'المملكه العربيه');

    runCorrection($file, new RecordingCorrectionProvider(fn () => unchangedAnswer('المملكة العربية', ['changes' => [
        ['original' => 'المملكه', 'corrected' => 'المملكة', 'type' => 'orthographic_normalization'],
        ['original' => 'العربيه', 'corrected' => 'العربية', 'type' => 'orthographic_normalization'],
    ]])));

    $correction = OcrCorrection::sole();
    expect($region->fresh()->source_text)->toBe('المملكه العربيه')
        ->and($region->corrections()->sole()->corrected_text)->toBe('المملكة العربية')
        ->and($correction->provider)->toBe('fake')
        ->and($correction->model)->toBe('fake-model')
        ->and($correction->model_version)->toBe('fake-model-2026')
        ->and($correction->prompt_version)->toBe('ocr-correction-v1')
        ->and($correction->rules_version)->toBe('guard-v1')
        ->and($correction->changes)->toHaveCount(2)
        ->and($correction->input_tokens)->toBe(100);
});

it('never pays twice: a re-run makes no calls, and identical masked text is one call across regions', function () {
    $file = correctionFile();
    // Same wording, different contact details: masks to the same text.
    ocrRegion($file, OcrRegionType::Footer, 'للتواصل a@one.example');
    ocrRegion($file, OcrRegionType::Footer, 'للتواصل b@two.example');
    ocrRegion($file, OcrRegionType::PrintedText, 'نص آخر');

    $provider = new RecordingCorrectionProvider;
    $first = runCorrection($file, $provider);
    $second = runCorrection($file, $provider);

    expect($first['provider_calls'])->toBe(2)
        ->and($first['cache_hits'])->toBe(1)
        ->and($second['provider_calls'])->toBe(0)
        ->and($second['cache_hits'])->toBe(3)
        ->and($provider->requests)->toHaveCount(2)
        ->and(OcrCorrection::count())->toBe(2)
        ->and(FileOcrRegionCorrection::count())->toBe(3)
        ->and(FileOcrRegionCorrection::pluck('corrected_text')->sort()->values()->all())->toBe(['للتواصل a@one.example', 'للتواصل b@two.example', 'نص آخر']);
});

it('calls again when the model changes, keeping the earlier layer', function () {
    $file = correctionFile();
    $region = ocrRegion($file, OcrRegionType::PrintedText, 'نص');

    runCorrection($file, new RecordingCorrectionProvider(modelName: 'model-a'));
    $provider = new RecordingCorrectionProvider(modelName: 'model-b');
    runCorrection($file, $provider);

    expect($provider->requests)->toHaveCount(1)
        ->and(OcrCorrection::pluck('model')->sort()->values()->all())->toBe(['model-a', 'model-b'])
        ->and($region->corrections()->count())->toBe(2);
});

it('stores an unusable answer as rejected, trusts none of it, and does not pay for it again', function () {
    $file = correctionFile();
    $region = ocrRegion($file, OcrRegionType::PrintedText, 'نص');

    $provider = new RecordingCorrectionProvider(fn () => ['corrected_text' => 'نص', 'confidence' => 'high']);
    runCorrection($file, $provider);
    runCorrection($file, $provider);

    $layer = $region->corrections()->sole();
    expect($provider->requests)->toHaveCount(1)
        ->and(OcrCorrection::sole()->status)->toBe('invalid_output')
        ->and($layer->status)->toBe('rejected')
        ->and($layer->corrected_text)->toBeNull()
        ->and($layer->needs_review)->toBeTrue()
        ->and($layer->review_reasons)->toBe(['schema_invalid']);
});

it('rejects an answer that lost a protected number, rather than restoring a guess', function () {
    $file = correctionFile();
    $region = ocrRegion($file, OcrRegionType::PrintedText, 'بتاريخ 1445/08/02 هـ');

    runCorrection($file, new RecordingCorrectionProvider(fn () => unchangedAnswer('بتاريخ هـ')));

    expect($region->corrections()->sole())
        ->status->toBe('rejected')
        ->corrected_text->toBeNull()
        ->review_reasons->toContain('placeholder_mismatch');
});

it('sends low self-reported confidence and the model\'s own doubts to review, never to trusted', function () {
    $file = correctionFile();
    $low = ocrRegion($file, OcrRegionType::PrintedText, 'نص أول');
    $doubt = ocrRegion($file, OcrRegionType::PrintedText, 'نص ثان');

    runCorrection($file, new RecordingCorrectionProvider(fn (string $text) => $text === 'نص أول'
        ? unchangedAnswer($text, ['confidence' => 0.6])
        : unchangedAnswer($text, ['needs_review' => true, 'reason' => 'OCR ambiguity'])));

    expect($low->corrections()->sole())->status->toBe('needs_review')->review_reasons->toBe(['low_model_confidence'])
        ->and($doubt->corrections()->sole())->status->toBe('needs_review')->review_reasons->toBe(['model_flagged']);
});

it('records text the model left alone as unchanged, not as corrected', function () {
    $file = correctionFile();
    $region = ocrRegion($file, OcrRegionType::PrintedText, 'هيئة الفنون البصرية');

    runCorrection($file, new RecordingCorrectionProvider);

    expect($region->corrections()->sole())->status->toBe('unchanged')->needs_review->toBeFalse();
});

it('never sends a region with a possible correction mark, even one whose flags wrongly allow it', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'SECRET old@example.com new@example.com', ['has_correction_mark' => true]);

    $provider = new RecordingCorrectionProvider;
    $summary = runCorrection($file, $provider);

    expect($provider->requests)->toBe([])->and($summary['skipped'])->toBe(['correction_mark' => 1]);
});

it('skips regions with nothing but numbers or contact details to correct', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::Footer, '+966500000000 info@gallery.example');

    $provider = new RecordingCorrectionProvider;
    $summary = runCorrection($file, $provider);

    expect($provider->requests)->toBe([])->and($summary['skipped'])->toBe(['nothing_to_correct' => 1]);
});

it('keeps finished regions when the provider fails mid-run, and resumes without re-sending them', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'الأول');
    ocrRegion($file, OcrRegionType::PrintedText, 'الثاني');
    ocrRegion($file, OcrRegionType::PrintedText, 'الثالث');

    $failing = new RecordingCorrectionProvider(failWhen: fn (string $text) => $text === 'الثاني');
    expect(fn () => runCorrection($file, $failing))->toThrow(OcrCorrectionException::class);
    expect(FileOcrRegionCorrection::count())->toBe(1);

    $healthy = new RecordingCorrectionProvider;
    $summary = runCorrection($file, $healthy);

    expect(collect($healthy->requests)->map(fn ($r) => sentText($r))->all())->toBe(['الثاني', 'الثالث'])
        ->and($summary['cache_hits'])->toBe(1)
        ->and(FileOcrRegionCorrection::count())->toBe(3);
});

it('reports what a dry run would send without calling the provider or writing anything', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص من خمس كلمات هنا');
    ocrRegion($file, OcrRegionType::Handwriting, 'ignored');

    $provider = new RecordingCorrectionProvider;
    $summary = runCorrection($file, $provider, dryRun: true);

    expect($provider->requests)->toBe([])
        ->and($summary['would_call'])->toBe(1)
        ->and($summary['would_send_chars'])->toBe(mb_strlen('نص من خمس كلمات هنا'))
        ->and(OcrCorrection::count())->toBe(0)
        ->and(FileOcrRegionCorrection::count())->toBe(0);
});

it('refuses the whole file unless enabled, configured, finished, and allowed by the privacy policy', function (Closure $arrange, string $reason) {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص');
    $provider = $arrange($file) ?? new RecordingCorrectionProvider;

    $summary = runCorrection($file, $provider);

    expect($summary['refused'])->toBe($reason)
        ->and($provider->requests)->toBe([]);
})->with([
    'disabled' => [fn () => config()->set('ocr.correction.enabled', false), 'disabled'],
    'OCR still running' => [function (File $f) {
        $f->update(['ocr_status' => 'processing']);

        return null;
    }, 'ocr_not_completed'],
    'external provider not opted in' => [fn () => new RecordingCorrectionProvider(external: true), 'external_provider_not_allowed'],
    'external provider, material above the ceiling' => [function (File $f) {
        config()->set('ocr.correction.allow_external_providers', true);
        $f->archiveItem->update(['access_level' => 'institution_only']);

        return new RecordingCorrectionProvider(external: true);
    }, 'access_level_not_allowed'],
    'external provider, embargoed material, whatever the ceiling' => [function (File $f) {
        config()->set('ocr.correction.allow_external_providers', true);
        config()->set('ocr.correction.external_max_access_level', 'institution_only');
        $f->archiveItem->update(['access_level' => 'embargoed', 'embargo_until' => now()->addYear()]);

        return new RecordingCorrectionProvider(external: true);
    }, 'access_level_not_allowed'],
]);

it('refuses to run against the null provider', function () {
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص');

    expect((new OcrCorrectionService(new NullCorrectionProvider))->correctFile($file)['refused'])->toBe('no_provider_configured');
});

it('sends public material to an opted-in external provider, and any material to a local one', function () {
    config()->set('ocr.correction.allow_external_providers', true);
    $public = correctionFile('public');
    ocrRegion($public, OcrRegionType::PrintedText, 'نص عام');
    $restricted = correctionFile('institution_only');
    ocrRegion($restricted, OcrRegionType::PrintedText, 'نص مقيد');

    $external = new RecordingCorrectionProvider(external: true);
    $local = new RecordingCorrectionProvider(external: false);

    expect(runCorrection($public, $external)['refused'])->toBeNull()
        ->and(runCorrection($restricted, $local)['refused'])->toBeNull()
        ->and($external->requests)->toHaveCount(1)
        ->and($local->requests)->toHaveCount(1);
});

it('logs every provider call with what produced it, never the text', function () {
    Log::spy();
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص سري للغاية');

    runCorrection($file, new RecordingCorrectionProvider);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'OCR correction provider call'
        && $context['provider'] === 'fake' && $context['model'] === 'fake-model' && $context['model_version'] === 'fake-model-2026'
        && $context['prompt_version'] === 'ocr-correction-v1' && $context['rules_version'] === 'guard-v1'
        && ! str_contains(json_encode($context, JSON_UNESCAPED_UNICODE), 'نص سري'))->once();
});

it('runs from the command line, with a dry run that sends nothing', function () {
    config()->set('ocr.correction.enabled', false);
    $file = correctionFile();
    ocrRegion($file, OcrRegionType::PrintedText, 'نص');

    $this->artisan('ocr:correct', ['file' => $file->id, '--dry-run' => true])
        ->expectsOutputToContain('Nothing sent: disabled.')
        ->assertSuccessful();

    config()->set('ocr.correction.enabled', true);
    $this->app->instance(OcrCorrectionProvider::class, new RecordingCorrectionProvider);
    $this->artisan('ocr:correct', ['file' => $file->id, '--dry-run' => true])
        ->expectsTable(['', 'count'], [['eligible regions', 1], ['cache hits', 0], ['would call provider', 1], ['characters that would be sent', 2]])
        ->assertSuccessful();

    $this->artisan('ocr:correct', ['file' => 999999])->assertFailed();
});
