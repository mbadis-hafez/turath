<?php

use App\Enums\ExtractedFieldStatus;
use App\Enums\FileOcrStatus;
use App\Enums\OcrRegionType;
use App\Enums\OcrStage;
use App\Jobs\CorrectOcrJob;
use App\Jobs\ExtractOcrFieldsJob;
use App\Jobs\ProcessFileOcrJob;
use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\FileExtractedField;
use App\Models\FileOcrRegion;
use App\Models\FileOcrStageRun;
use App\Support\Ocr\Correction\OcrCorrectionException;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\Correction\OcrCorrectionService;
use App\Support\Ocr\Correction\ProviderRequest;
use App\Support\Ocr\Correction\ProviderResponse;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\OcrEngineException;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/** Reads every page as the same text, and counts how often it was asked — each count is a real tesseract run. */
class PipelineOcrEngine implements OcrEngine
{
    public int $calls = 0;

    public function __construct(public string $en = 'Visual Arts Exhibition 1985', public ?string $failWith = null) {}

    public function recognize(string $imagePath, string $language): array
    {
        $this->calls++;
        if ($this->failWith !== null) {
            throw new OcrEngineException($this->failWith);
        }

        return ['text' => $language === 'ar' ? 'معرض الفنون التشكيلية' : $this->en, 'confidence' => 90, 'segments' => []];
    }
}

class PipelineNoPdf implements PdfPageRasterizer
{
    public function rasterize(string $pdfPath, string $outputDir): array
    {
        return [];
    }
}

class PipelineNoLayout implements PageLayoutAnalyzer
{
    public function analyze(string $imagePath): array
    {
        return [];
    }
}

class PipelineNoBlobs implements NonTextRegionDetector
{
    public function detect(string $imagePath, array $occupiedBboxes): array
    {
        return [];
    }
}

/** Echoes the text back unchanged; $failOn decides, per attempted call, whether to throw instead. */
class PipelineCorrectionProvider implements OcrCorrectionProvider
{
    /** Calls answered — the ones that would have been paid for. */
    public int $answered = 0;

    public int $attempted = 0;

    /** @param  (Closure(int): ?OcrCorrectionException)|null  $failOn  attempted-call number => exception */
    public function __construct(public ?Closure $failOn = null) {}

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function isExternal(): bool
    {
        return false;
    }

    public function correct(ProviderRequest $request): ProviderResponse
    {
        $this->attempted++;
        $failure = $this->failOn !== null ? ($this->failOn)($this->attempted) : null;
        if ($failure !== null) {
            throw $failure;
        }
        $this->answered++;
        preg_match('~<ocr_text>\n(.*)\n</ocr_text>~su', $request->user, $m);
        $payload = ['corrected_text' => $m[1], 'changes' => [], 'name_candidates' => [], 'confidence' => 0.95, 'needs_review' => false, 'reason' => null];

        return new ProviderResponse($payload, ['echo' => true], 'fake-model-2026', 10, 5, null);
    }
}

function pipelineFile(array $attributes = []): File
{
    $item = ArchiveItem::factory()->create(['access_level' => 'public']);

    return File::factory()->create(['archive_item_id' => $item->id, 'mime_type' => 'image/jpeg', 'path' => 'archive/pipeline.jpg', 'ocr_status' => null, ...$attributes]);
}

function pipeline(): OcrPipeline
{
    return app(OcrPipeline::class);
}

/** Works the queue like a worker: the recognize job queued last (with fake engines), then every stage after it. */
function workPipeline(PipelineOcrEngine $engine): void
{
    Queue::pushed(ProcessFileOcrJob::class)->last()?->handle($engine, new PipelineNoPdf, new PipelineNoLayout, new PipelineNoBlobs);
    runQueuedOcrStages();
}

function stageRun(File $file, OcrStage $stage): FileOcrStageRun
{
    return $file->ocrStageRuns()->where('stage', $stage->value)->sole();
}

function printedRegion(File $file, string $text): FileOcrRegion
{
    return FileOcrRegion::create([
        'file_id' => $file->id, 'page_number' => 1, 'region_type' => OcrRegionType::PrintedText->value, 'language' => 'ar',
        'bbox' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10], 'confidence' => 85, 'source_text' => $text,
        'ocr_allowed' => true, 'ai_correction_allowed' => true, 'requires_human_review' => false, 'review_reason' => null,
    ]);
}

/**
 * A file through recognize and extract, with two printed regions, and AI
 * correction then switched on with $provider and its stage queued.
 */
function fileAwaitingCorrection(PipelineCorrectionProvider $provider): File
{
    $file = pipelineFile();
    pipeline()->start($file);
    workPipeline(new PipelineOcrEngine);
    printedRegion($file, 'معرض الفنون التشكيليه');
    printedRegion($file, 'جمعيه الثقافه والفنون');

    config()->set('ocr.correction.enabled', true);
    config()->set('ocr.correction.review_below_confidence', 0.85);
    app()->instance(OcrCorrectionProvider::class, $provider);

    expect(pipeline()->start($file->refresh(), OcrStage::Correct))->toBe(OcrPipeline::STARTED);

    return $file;
}

/** The queued correction job as a worker's Nth attempt, with the queue's release/fail calls recorded. */
function correctionAttempt(int $attempt): CorrectOcrJob
{
    $job = Queue::pushed(CorrectOcrJob::class)->last()->withFakeQueueInteractions();
    $job->job->attempts = $attempt;
    app()->call([$job, 'handle']);

    return $job;
}

describe('running the stages', function () {
    it('runs recognize then extract as separate jobs, completes the file, and records every stage', function () {
        $file = pipelineFile();

        expect(pipeline()->start($file))->toBe(OcrPipeline::STARTED)
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Pending);
        Queue::assertPushed(ProcessFileOcrJob::class, 1);
        Queue::assertNotPushed(ExtractOcrFieldsJob::class);

        workPipeline(new PipelineOcrEngine);
        $file->refresh();

        expect($file->ocr_status)->toBe(FileOcrStatus::Completed)
            ->and($file->ocr_completed_at)->not->toBeNull()
            ->and($file->document_type)->not->toBeNull()
            ->and($file->extractedFields()->where('field_key', 'title_en')->value('extracted_value'))->toBe('Visual Arts Exhibition 1985');

        $recognize = stageRun($file, OcrStage::Recognize);
        $extract = stageRun($file, OcrStage::Extract);
        expect($recognize->status)->toBe('succeeded')
            ->and($recognize->attempts)->toBe(1)
            ->and($recognize->summary['pages'])->toBe(1)
            ->and($recognize->input_fingerprint)->toHaveLength(64)
            ->and($extract->status)->toBe('succeeded')
            ->and($extract->summary['candidate_fields'])->toBeGreaterThan(0)
            ->and($extract->run_id)->toBe($recognize->run_id);

        // Correction is off: skipped with the reason, and no job was queued just to find that out.
        expect(stageRun($file, OcrStage::Correct)->status)->toBe('skipped')
            ->and(stageRun($file, OcrStage::Correct)->reason)->toBe('disabled');
        Queue::assertNotPushed(CorrectOcrJob::class);
    });

    it('does nothing for a file whose stages are all up to date: no OCR, no extraction', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        workPipeline($engine = new PipelineOcrEngine);
        $completedAt = $file->refresh()->ocr_completed_at;

        expect(pipeline()->start($file))->toBe(OcrPipeline::UP_TO_DATE);

        Queue::assertPushed(ProcessFileOcrJob::class, 1);
        Queue::assertPushed(ExtractOcrFieldsJob::class, 1);
        expect($engine->calls)->toBe(2)
            ->and(stageRun($file, OcrStage::Recognize)->reason)->toBe('up_to_date')
            ->and(stageRun($file, OcrStage::Extract)->reason)->toBe('up_to_date')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed)
            ->and($file->ocr_completed_at->equalTo($completedAt))->toBeTrue();
    });

    it('re-runs extraction on its own, without OCR, keeping fields a reviewer already decided', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        workPipeline($engine = new PipelineOcrEngine);
        $file->extractedFields()->where('field_key', 'title_en')->update(['status' => ExtractedFieldStatus::Accepted->value, 'extracted_value' => 'Reviewer wording']);

        expect(pipeline()->start($file, OcrStage::Extract, force: true))->toBe(OcrPipeline::STARTED);
        Queue::assertPushed(ProcessFileOcrJob::class, 1);
        runQueuedOcrStages();

        $extract = stageRun($file, OcrStage::Extract);
        expect($engine->calls)->toBe(2)
            ->and(stageRun($file, OcrStage::Recognize)->status)->toBe('succeeded')
            ->and($extract->status)->toBe('succeeded')
            ->and($extract->summary['reviewed_fields_kept'])->toBe(1)
            ->and(FileExtractedField::query()->where('file_id', $file->id)->where('field_key', 'title_en')->sole()->extracted_value)->toBe('Reviewer wording')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed);
    });

    it('after a forced re-OCR, re-extracts only when the text actually changed', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        workPipeline(new PipelineOcrEngine);

        pipeline()->start($file, OcrStage::Recognize, force: true);
        workPipeline(new PipelineOcrEngine);
        Queue::assertPushed(ExtractOcrFieldsJob::class, 1);
        expect(stageRun($file, OcrStage::Extract)->reason)->toBe('up_to_date');

        pipeline()->start($file, OcrStage::Recognize, force: true);
        workPipeline(new PipelineOcrEngine(en: 'Sculpture Symposium 1992'));
        Queue::assertPushed(ExtractOcrFieldsJob::class, 2);
        expect($file->extractedFields()->where('field_key', 'title_en')->value('extracted_value'))->toBe('Sculpture Symposium 1992');
    });

    it('plans without doing anything, and sees a changed file or a stale stage', function () {
        $file = pipelineFile();
        expect(pipeline()->plan($file))->toBe(['recognize' => 'will_run', 'extract' => 'after_earlier_stages', 'match' => 'after_earlier_stages', 'correct' => 'after_earlier_stages']);

        pipeline()->start($file);
        workPipeline(new PipelineOcrEngine);
        expect(pipeline()->plan($file))->toBe(['recognize' => 'up_to_date', 'extract' => 'up_to_date', 'match' => 'up_to_date', 'correct' => 'skip:disabled']);

        $file->update(['sha256' => str_repeat('b', 64)]);
        expect(pipeline()->plan($file)['recognize'])->toBe('will_run');
    });
});

describe('idempotency and recovery', function () {
    it('lets a queued job from a superseded run do nothing', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        $this->travel(2)->hours(); // the first job was lost from the queue
        expect(pipeline()->start($file))->toBe(OcrPipeline::STARTED);
        [$lost, $current] = Queue::pushed(ProcessFileOcrJob::class)->values()->all();

        $engine = new PipelineOcrEngine;
        $lost->handle($engine, new PipelineNoPdf, new PipelineNoLayout, new PipelineNoBlobs);
        expect($engine->calls)->toBe(0);

        $current->handle($engine, new PipelineNoPdf, new PipelineNoLayout, new PipelineNoBlobs);
        expect($engine->calls)->toBe(2);
    });

    it('ignores a second delivery of a stage that is running, and takes over one whose worker died', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        $job = Queue::pushed(ProcessFileOcrJob::class)->last();
        pipeline()->claim($file, OcrStage::Recognize, $job->runId); // the first delivery, still working

        $engine = new PipelineOcrEngine;
        $job->handle($engine, new PipelineNoPdf, new PipelineNoLayout, new PipelineNoBlobs);
        expect($engine->calls)->toBe(0);

        $this->travel(1800 + 301)->seconds(); // past the stage's timeout: that worker is gone
        $job->handle($engine, new PipelineNoPdf, new PipelineNoLayout, new PipelineNoBlobs);
        expect($engine->calls)->toBe(2)
            ->and(stageRun($file, OcrStage::Recognize)->status)->toBe('succeeded');
    });

    it('fails the file when a core stage fails, stops there, and allows starting again', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        workPipeline(new PipelineOcrEngine(failWith: 'tesseract binary not found'));

        expect($file->refresh()->ocr_status)->toBe(FileOcrStatus::Failed)
            ->and($file->ocr_failure_reason)->toContain('tesseract binary not found')
            ->and(stageRun($file, OcrStage::Recognize)->status)->toBe('failed')
            ->and(stageRun($file, OcrStage::Recognize)->error)->toContain('OcrEngineException')
            ->and(stageRun($file, OcrStage::Extract)->reason)->toBe('upstream_failed')
            ->and(stageRun($file, OcrStage::Correct)->reason)->toBe('upstream_failed');
        Queue::assertNotPushed(ExtractOcrFieldsJob::class);

        expect(pipeline()->start($file))->toBe(OcrPipeline::STARTED);
        workPipeline(new PipelineOcrEngine);
        expect($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed);
    });

    it('records a timed-out stage as failed, but leaves a running stage alone when only a redelivery gave up', function () {
        $file = pipelineFile();
        pipeline()->start($file);
        $job = Queue::pushed(ProcessFileOcrJob::class)->last();
        pipeline()->claim($file, OcrStage::Recognize, $job->runId);

        $job->failed(new MaxAttemptsExceededException('attempted too many times'));
        expect(stageRun($file, OcrStage::Recognize)->status)->toBe('running');

        $job->failed(new TimeoutExceededException('has timed out'));
        expect(stageRun($file, OcrStage::Recognize)->status)->toBe('failed')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Failed);
    });
});

describe('AI correction as a stage', function () {
    it('corrects once, and re-links from cache with no provider calls after regions are recreated', function () {
        $provider = new PipelineCorrectionProvider;
        $file = fileAwaitingCorrection($provider);

        correctionAttempt(1)->assertNotFailed()->assertNotReleased();
        $correct = stageRun($file, OcrStage::Correct);
        expect($provider->answered)->toBe(2)
            ->and($correct->status)->toBe('succeeded')
            ->and($correct->summary)->toMatchArray(['eligible' => 2, 'provider_calls' => 2, 'cache_hits' => 0]);

        // Unchanged: nothing queued.
        expect(pipeline()->start($file, OcrStage::Correct))->toBe(OcrPipeline::UP_TO_DATE);
        Queue::assertPushed(CorrectOcrJob::class, 1);

        // Re-running OCR recreates the same regions under new ids: the stage runs again, but pays nothing.
        foreach ($file->ocrRegions()->get() as $region) {
            $region->delete();
            printedRegion($file, $region->source_text);
        }
        expect(pipeline()->start($file, OcrStage::Correct))->toBe(OcrPipeline::STARTED);
        correctionAttempt(1);

        expect($provider->answered)->toBe(2)
            ->and(stageRun($file, OcrStage::Correct)->summary)->toMatchArray(['provider_calls' => 0, 'cache_hits' => 2])
            ->and($file->ocrRegions()->withCount('corrections')->get()->pluck('corrections_count')->all())->toBe([1, 1]);
    });

    it('retries a rate limit with backoff, resuming without paying again for regions already done', function () {
        $provider = new PipelineCorrectionProvider(fn (int $call) => $call === 2 ? new OcrCorrectionException('HTTP 429 rate limited', true) : null);
        $file = fileAwaitingCorrection($provider);

        correctionAttempt(1)->assertReleased(30);
        $correct = stageRun($file, OcrStage::Correct);
        expect($correct->status)->toBe('queued')
            ->and($correct->reason)->toBe('retrying')
            ->and($correct->error)->toContain('HTTP 429')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed);

        correctionAttempt(2)->assertNotReleased()->assertNotFailed();
        expect($provider->answered)->toBe(2)
            ->and(stageRun($file, OcrStage::Correct)->status)->toBe('succeeded')
            ->and(stageRun($file, OcrStage::Correct)->attempts)->toBe(2)
            ->and(stageRun($file, OcrStage::Correct)->summary)->toMatchArray(['provider_calls' => 1, 'cache_hits' => 1]);
    });

    it('never fails the file when correction fails: a bad request fails the stage at once, a rate limit after its last attempt', function () {
        $provider = new PipelineCorrectionProvider(fn () => new OcrCorrectionException('HTTP 400 invalid request', false));
        $file = fileAwaitingCorrection($provider);

        correctionAttempt(1)->assertFailed();
        expect(stageRun($file, OcrStage::Correct)->status)->toBe('failed')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed)
            ->and($file->extractedFields()->count())->toBeGreaterThan(0);

        $provider->failOn = fn () => new OcrCorrectionException('HTTP 429 rate limited', true);
        pipeline()->start($file, OcrStage::Correct, force: true);
        correctionAttempt(5)->assertFailed();
        expect(stageRun($file, OcrStage::Correct)->status)->toBe('failed')
            ->and($file->refresh()->ocr_status)->toBe(FileOcrStatus::Completed);
    });

    it('does not call the provider while another worker is correcting the same text; it retries later instead', function () {
        $provider = new PipelineCorrectionProvider;
        $file = fileAwaitingCorrection($provider);
        config()->set('ocr.correction.lock_wait_seconds', 0);
        $first = $file->ocrRegions()->orderBy('id')->first();
        Cache::lock(OcrCorrectionService::lockName(app(OcrCorrectionService::class)->cacheKeyFor($first)), 60)->get();

        correctionAttempt(1)->assertReleased(30);

        expect($provider->attempted)->toBe(0)
            ->and(stageRun($file, OcrStage::Correct)->error)->toContain('Another worker');
    });
});

describe('api and command', function () {
    it('lists every stage in the review bundle', function () {
        $editor = editorUser();
        $file = pipelineFile();
        pipeline()->start($file);
        workPipeline(new PipelineOcrEngine);

        $stages = $this->actingAs($editor)->getJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr")->assertOk()->json('data.stages');

        expect(array_column($stages, 'stage'))->toBe(['recognize', 'extract', 'match', 'correct'])
            ->and(array_column($stages, 'status'))->toBe(['succeeded', 'succeeded', 'succeeded', 'skipped'])
            ->and(array_column($stages, 'can_run'))->toBe([true, true, true, true])
            ->and($stages[0]['summary']['pages'])->toBe(1);
    });

    it('re-runs one stage through the api, refusing while busy or before the stages it needs', function () {
        $editor = editorUser();
        $fresh = pipelineFile();
        $this->actingAs($editor)->postJson("/api/v1/archive-items/{$fresh->archive_item_id}/file/ocr/stages/extract/run")->assertUnprocessable();

        // OCR'd before stage tracking existed: its text is there to extract from.
        $legacy = pipelineFile(['ocr_status' => FileOcrStatus::Completed->value]);
        $this->actingAs($editor)->postJson("/api/v1/archive-items/{$legacy->archive_item_id}/file/ocr/stages/extract/run")
            ->assertOk()->assertJsonPath('data.result', 'started')->assertJsonCount(4, 'data.stages');
        Queue::assertPushed(ExtractOcrFieldsJob::class, 1);
        Queue::assertNotPushed(ProcessFileOcrJob::class);

        pipeline()->start($fresh);
        $this->actingAs($editor)->postJson("/api/v1/archive-items/{$fresh->archive_item_id}/file/ocr/stages/recognize/run")->assertStatus(409);
        $this->actingAs($editor)->postJson("/api/v1/archive-items/{$fresh->archive_item_id}/file/ocr/stages/bogus/run")->assertNotFound();
    });

    it('requires archive.manage to run a stage', function () {
        $file = pipelineFile(['ocr_status' => FileOcrStatus::Completed->value]);

        $this->actingAs(makeUser())->postJson("/api/v1/archive-items/{$file->archive_item_id}/file/ocr/stages/extract/run")->assertForbidden();
        Queue::assertNothingPushed();
    });

    it('plans with --dry-run without queueing, then queues only image and PDF files', function () {
        $file = pipelineFile();
        $video = pipelineFile(['mime_type' => 'video/mp4']);

        $this->artisan('ocr:pipeline', ['files' => [$file->id, $video->id], '--dry-run' => true])
            ->expectsOutputToContain('will_run')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('ocr:pipeline', ['--all' => true])->assertSuccessful();
        Queue::assertPushed(ProcessFileOcrJob::class, 1);

        $this->artisan('ocr:pipeline', ['--all' => true, '--from' => 'nope'])->assertFailed();
    });
});
