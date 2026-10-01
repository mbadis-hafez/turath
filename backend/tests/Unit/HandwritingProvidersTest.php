<?php

use App\Support\Ocr\AzureHandwritingOcrProvider;
use App\Support\Ocr\CropLineSplitter;
use App\Support\Ocr\HandwritingOcrException;
use App\Support\Ocr\HandwritingOcrProvider;
use App\Support\Ocr\HandwritingProviderFactory;
use App\Support\Ocr\KrakenHandwritingOcrProvider;
use App\Support\Ocr\NullHandwritingOcrProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/** A white image with a dark bar per [top, height] band — a stand-in for lines of writing. */
function linesImage(array $bands, int $width = 300, int $height = 160): GdImage
{
    $im = imagecreatetruecolor($width, $height);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $ink = imagecolorallocate($im, 20, 20, 80);
    foreach ($bands as [$top, $bandHeight]) {
        for ($x = 20; $x < $width - 20; $x += 9) {
            imagefilledrectangle($im, $x, $top, $x + 5, $top + $bandHeight - 1, $ink);
        }
    }

    return $im;
}

function cropFile(GdImage $im): string
{
    $path = tempnam(sys_get_temp_dir(), 'hwcrop').'.png';
    imagepng($im, $path);

    return $path;
}

/**
 * An executable stand-in for kraken 7: records its argv, and for each
 * `-i input output` pair writes ALTO shaped like kraken's (String with
 * CONTENT and WC), or exits non-zero when told to.
 */
function fakeKraken(string $mode = 'ok'): array
{
    $dir = sys_get_temp_dir().'/fake-kraken-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $script = <<<'PHP'
<?php
$dir = __DIR__;
file_put_contents("$dir/argv.json", json_encode(array_slice($argv, 1)));
if (getenv('FAKE_KRAKEN_MODE') === 'fail' || file_exists("$dir/fail")) { fwrite(STDERR, "model not found\n"); exit(1); }
$args = array_slice($argv, 1);
$n = 0;
for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '-i') {
        $n++;
        $out = $args[$i + 2];
        file_put_contents($out, '<?xml version="1.0"?><alto xmlns="http://www.loc.gov/standards/alto/ns-v4#"><Layout><Page><PrintSpace><TextBlock><TextLine>'
            ."<String ID=\"segment_0\" CONTENT=\"سطر\" WC=\"0.8\"/><SP/><String ID=\"segment_2\" CONTENT=\"{$n}\" WC=\"0.4\"/>"
            .'</TextLine></TextBlock></PrintSpace></Page></Layout></alto>');
        $i += 2;
    }
}
PHP;
    file_put_contents("$dir/kraken.php", $script);
    file_put_contents("$dir/kraken", "#!/bin/sh\nexec \"".PHP_BINARY."\" \"$dir/kraken.php\" \"\$@\"\n");
    chmod("$dir/kraken", 0755);
    file_put_contents("$dir/model.mlmodel", 'weights');
    if ($mode === 'fail') {
        touch("$dir/fail");
    }

    return ['binary' => "$dir/kraken", 'model' => "$dir/model.mlmodel", 'dir' => $dir];
}

describe('line splitting', function () {
    it('keeps a single line whole', function () {
        expect((new CropLineSplitter)->split(linesImage([[60, 40]])))->toHaveCount(1);
    });

    it('splits separate lines of writing', function () {
        $lines = (new CropLineSplitter)->split(linesImage([[10, 35], [70, 35], [120, 35]], height: 170));

        expect($lines)->toHaveCount(3);
    });

    it('keeps a fragment wrapped under a line with that line, like the benchmark\'s "yah" + "oo"', function () {
        expect((new CropLineSplitter)->split(linesImage([[30, 40], [76, 10]])))->toHaveCount(1);
    });
});

describe('kraken', function () {
    it('recognizes each line with segmentation off, in logical Arabic order, and reports the model checksum', function () {
        $fake = fakeKraken();
        $provider = new KrakenHandwritingOcrProvider($fake['model'], $fake['binary']);

        $suggestion = $provider->suggest(cropFile(linesImage([[10, 35], [80, 35]])), null);

        $argv = json_decode(file_get_contents("{$fake['dir']}/argv.json"), true);
        expect(array_count_values($argv)['-i'])->toBe(2)
            ->and(array_slice($argv, -8))->toBe(['-a', 'ocr', '-s', '--reorder', '--base-dir', 'R', '-m', $fake['model']])
            ->and($suggestion->text)->toBe("سطر 1\nسطر 2")
            ->and($suggestion->confidence)->toBe(0.6)
            ->and($suggestion->modelVersion)->toStartWith('sha256:')
            ->and($suggestion->raw['lines'])->toHaveCount(2)
            ->and($provider->isExternal())->toBeFalse()
            ->and($provider->model())->toBe('model.mlmodel');
    });

    it('fails without storing anything when kraken fails or the crop cannot be read', function () {
        $fake = fakeKraken('fail');
        $provider = new KrakenHandwritingOcrProvider($fake['model'], $fake['binary']);

        expect(fn () => $provider->suggest(cropFile(linesImage([[60, 40]])), null))
            ->toThrow(fn (HandwritingOcrException $e) => expect($e->retryable)->toBeFalse()->and($e->getMessage())->toContain('model not found'))
            ->and(fn () => $provider->suggest('/nonexistent/crop.png', null))
            ->toThrow(HandwritingOcrException::class);
    });
});

describe('azure', function () {
    beforeEach(fn () => Sleep::fake());

    function azure(): AzureHandwritingOcrProvider
    {
        return new AzureHandwritingOcrProvider('https://di.example.test/', 'secret-key', maxPolls: 3);
    }

    function analyzeResult(): array
    {
        return [
            'apiVersion' => '2024-11-30', 'modelId' => 'prebuilt-read', 'content' => "أحمد المغلوث\nالأحساء",
            'pages' => [['pageNumber' => 1, 'words' => [
                ['content' => 'أحمد', 'confidence' => 0.91, 'polygon' => [1, 2, 3, 4]],
                ['content' => 'المغلوث', 'confidence' => 0.63, 'polygon' => [1, 2, 3, 4]],
                ['content' => 'الأحساء', 'confidence' => 0.8, 'polygon' => [1, 2, 3, 4]],
            ]]],
            'styles' => [['isHandwritten' => true, 'confidence' => 0.95, 'spans' => [['offset' => 0, 'length' => 20]]]],
        ];
    }

    it('submits the crop, polls the operation until it succeeds, and returns the reading', function () {
        Http::fake([
            'di.example.test/documentintelligence/documentModels/prebuilt-read:analyze*' => Http::response(null, 202, ['Operation-Location' => 'https://di.example.test/documentintelligence/documentModels/prebuilt-read/analyzeResults/abc?api-version=2024-11-30']),
            'di.example.test/documentintelligence/documentModels/prebuilt-read/analyzeResults/*' => Http::sequence()
                ->push(['status' => 'running'])
                ->push(['status' => 'succeeded', 'analyzeResult' => analyzeResult()]),
        ]);

        $suggestion = azure()->suggest(cropFile(linesImage([[60, 40]])), null);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_contains($r->url(), 'prebuilt-read:analyze?api-version=2024-11-30')
            && $r->hasHeader('Ocp-Apim-Subscription-Key', 'secret-key')
            && base64_decode($r['base64Source'], true) !== false
            && ! str_contains($r->url(), 'locale'));
        expect($suggestion->text)->toBe("أحمد المغلوث\nالأحساء")
            ->and($suggestion->confidence)->toBe(0.78)
            ->and($suggestion->modelVersion)->toBe('prebuilt-read@2024-11-30')
            ->and($suggestion->raw['styles'][0]['is_handwritten'])->toBeTrue()
            ->and(json_encode($suggestion->raw))->not->toContain('polygon');
        Sleep::assertSleptTimes(2);
    });

    it('treats a failed analysis as final', function () {
        Http::fake(['*/prebuilt-read:analyze*' => Http::response(null, 202, ['Operation-Location' => 'https://di.example.test/op/1']), 'di.example.test/op/*' => Http::response(['status' => 'failed', 'error' => ['message' => 'InvalidImage']])]);

        expect(fn () => azure()->suggest(cropFile(linesImage([[60, 40]])), null))
            ->toThrow(fn (HandwritingOcrException $e) => expect($e->retryable)->toBeFalse()->and($e->getMessage())->toContain('InvalidImage'));
    });

    it('treats a rate limit as retryable', function () {
        Http::fake(['*' => Http::response(['error' => ['code' => '429']], 429)]);

        expect(fn () => azure()->suggest(cropFile(linesImage([[60, 40]])), null))
            ->toThrow(fn (HandwritingOcrException $e) => expect($e->retryable)->toBeTrue());
    });

    it('gives up after the configured number of polls', function () {
        Http::fake(['*/prebuilt-read:analyze*' => Http::response(null, 202, ['Operation-Location' => 'https://di.example.test/op/2']), 'di.example.test/op/*' => Http::response(['status' => 'running'])]);

        expect(fn () => azure()->suggest(cropFile(linesImage([[60, 40]])), null))
            ->toThrow(fn (HandwritingOcrException $e) => expect($e->retryable)->toBeTrue()->and($e->getMessage())->toContain('3 polls'));
    });
});

describe('factory', function () {
    it('gives manual transcription only unless a provider is enabled and configured', function () {
        expect(HandwritingProviderFactory::make(['enabled' => false, 'provider' => 'kraken']))->toBeInstanceOf(NullHandwritingOcrProvider::class)
            ->and(app(HandwritingOcrProvider::class))->toBeInstanceOf(NullHandwritingOcrProvider::class)
            ->and(fn () => HandwritingProviderFactory::make(['enabled' => true, 'provider' => 'kraken', 'providers' => ['kraken' => []]]))
            ->toThrow(InvalidArgumentException::class, 'OCR_HANDWRITING_KRAKEN_MODEL')
            ->and(HandwritingProviderFactory::make(['enabled' => true, 'provider' => 'azure', 'providers' => ['azure' => ['endpoint' => 'https://x.test', 'key' => 'k']]])->isExternal())
            ->toBeTrue();
    });
});
