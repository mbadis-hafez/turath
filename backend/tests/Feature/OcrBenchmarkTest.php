<?php

use App\Models\ArchiveItem;
use App\Models\File;
use App\Models\OcrEvaluationReservedFile;
use App\Support\Ocr\Benchmark\BenchmarkDocument;
use App\Support\Ocr\Benchmark\BenchmarkManifest;
use App\Support\Ocr\Benchmark\BenchmarkRunner;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PageLayoutAnalyzer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Queue;

/**
 * The benchmark suite: a synthetic biography run through the real pipeline
 * (OCR engine and layout faked), scored against known answers, with nothing
 * left behind and no document text in the report.
 */

/** Reads a short artist biography; English pages are empty. */
class BenchmarkBiographyEngine implements OcrEngine
{
    public const TEXT = "السيرة الذاتية\nالفنان: سارة الراشد\nمكان الميلاد: الرياض\nولد عام ١٩٥٠";

    public function recognize(string $imagePath, string $language): array
    {
        return ['text' => $language === 'ar' ? self::TEXT : '', 'confidence' => 88, 'segments' => []];
    }
}

class BenchmarkNoLayout implements PageLayoutAnalyzer
{
    public function analyze(string $imagePath): array
    {
        return [];
    }
}

class BenchmarkNoBlobs implements NonTextRegionDetector
{
    public function detect(string $imagePath, array $occupiedBboxes): array
    {
        return [];
    }
}

/** A suite directory with one scan and its manifest; returns the manifest path. */
function benchmarkSuite(array $documents): string
{
    $dir = storage_path('framework/testing/benchmark-'.uniqid());
    Filesystem::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/biography.png", 'synthetic-scan-'.uniqid());
    file_put_contents("{$dir}/manifest.json", json_encode(['documents' => $documents], JSON_UNESCAPED_UNICODE));

    return "{$dir}/manifest.json";
}

function biographyEntry(array $expected = []): array
{
    return [
        'id' => 'biography-1', 'category' => 'artist_biography', 'file' => 'biography.png', 'contains_personal_data' => false,
        'expected' => [
            'document_type' => 'artist_biography',
            'fields' => ['artist' => 'سارة الراشد', 'birth_place' => 'الرياض'],
            'pages' => [['page' => 1, 'language' => 'ar', 'text' => BenchmarkBiographyEngine::TEXT]],
            'matches' => [['entity_type' => 'artist', 'source_text' => 'سارة الراشد', 'entity_id' => null]],
            ...$expected,
        ],
    ];
}

beforeEach(function () {
    app()->instance(OcrEngine::class, new BenchmarkBiographyEngine);
    app()->instance(PageLayoutAnalyzer::class, new BenchmarkNoLayout);
    app()->instance(NonTextRegionDetector::class, new BenchmarkNoBlobs);
    // The suite runs every stage inline: let jobs through to the (sync) queue.
    Queue::fake(['jobs that are never dispatched']);
});

it('runs a document through every stage and scores it against its known answers', function () {
    $manifest = benchmarkSuite([biographyEntry()]);
    $report = storage_path('framework/testing/report-'.uniqid().'.json');

    expect(Artisan::call('ocr:benchmark', ['--manifest' => $manifest, '--report' => $report]))->toBe(0);
    $result = json_decode((string) file_get_contents($report), true);
    $doc = $result['documents_detail'][0];

    expect($doc['document_type'])->toMatchArray(['expected' => 'artist_biography', 'observed' => 'artist_biography', 'correct' => true])
        ->and($doc['fields']['by_key'])->toMatchArray(['artist' => 'found', 'birth_place' => 'found'])
        ->and($doc['fields']['recall'])->toBe(1)
        ->and($doc['pages']['cer'])->toBe(0)
        ->and($doc['matches'])->toMatchArray(['checked' => 1, 'correct_match_rate' => 1])
        ->and($doc['stages'])->toMatchArray(['recognize' => 'succeeded', 'extract' => 'succeeded'])
        ->and($result['overall']['document_type_accuracy'])->toBe(1)
        ->and($result['coverage']['covered'])->toBe(1)
        ->and($result['coverage']['missing'])->toContain('handwritten_form');
    Filesystem::deleteDirectory(dirname($manifest));
    @unlink($report);
});

it('keeps nothing from the run, and reserves the file for evaluation', function () {
    $manifest = benchmarkSuite([biographyEntry()]);
    $items = ArchiveItem::count();
    $files = File::count();

    Artisan::call('ocr:benchmark', ['--manifest' => $manifest, '--report' => storage_path('framework/testing/report-'.uniqid().'.json')]);

    expect(ArchiveItem::count())->toBe($items)
        ->and(File::count())->toBe($files)
        ->and(Filesystem::glob(storage_path('app/ocr-benchmark-runs/*')))->toBe([])
        ->and(OcrEvaluationReservedFile::where('sha256', hash_file('sha256', dirname($manifest).'/biography.png'))->exists())->toBeTrue();
    Filesystem::deleteDirectory(dirname($manifest));
});

it('reports counts and keys, never the document\'s text', function () {
    $manifest = benchmarkSuite([biographyEntry(['fields' => ['artist' => 'منى السالم', 'birth_place' => 'الرياض']])]);
    $report = storage_path('framework/testing/report-'.uniqid().'.json');

    Artisan::call('ocr:benchmark', ['--manifest' => $manifest, '--report' => $report]);
    $json = (string) file_get_contents($report);

    expect(json_decode($json, true)['documents_detail'][0]['fields']['by_key'])->toMatchArray(['artist' => 'wrong', 'birth_place' => 'found'])
        ->and($json.Artisan::output())->not->toContain('سارة')
        ->and($json)->not->toContain('منى')
        ->and($json)->not->toContain('الرياض');
    Filesystem::deleteDirectory(dirname($manifest));
    @unlink($report);
});

it('refuses to run in production', function () {
    $manifest = benchmarkSuite([biographyEntry()]);
    app()->detectEnvironment(fn () => 'production');

    expect(Artisan::call('ocr:benchmark', ['--manifest' => $manifest]))->toBe(1)
        ->and(fn () => app(BenchmarkRunner::class)->run(new BenchmarkDocument('x', 'artist_biography', dirname($manifest).'/biography.png', false, [])))
        ->toThrow(RuntimeException::class);
    app()->detectEnvironment(fn () => 'testing');
    Filesystem::deleteDirectory(dirname($manifest));
});

it('rejects a manifest with an unknown category or a missing file', function () {
    $manifest = benchmarkSuite([[...biographyEntry(), 'category' => 'novel']]);
    expect(fn () => BenchmarkManifest::load($manifest))->toThrow(InvalidArgumentException::class, 'needs a category');

    $missing = benchmarkSuite([[...biographyEntry(), 'file' => 'nowhere.pdf']]);
    expect(fn () => BenchmarkManifest::load($missing))->toThrow(InvalidArgumentException::class, 'file not found');
    Filesystem::deleteDirectory(dirname($manifest));
    Filesystem::deleteDirectory(dirname($missing));
});
