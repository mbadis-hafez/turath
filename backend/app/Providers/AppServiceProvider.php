<?php

namespace App\Providers;

use App\Support\Ocr\Correction\CorrectionProviderFactory;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\CorrectionMarkDetector;
use App\Support\Ocr\GdCorrectionMarkDetector;
use App\Support\Ocr\GdNonTextRegionDetector;
use App\Support\Ocr\HandwritingOcrProvider;
use App\Support\Ocr\HandwritingProviderFactory;
use App\Support\Ocr\NonTextRegionDetector;
use App\Support\Ocr\OcrEngine;
use App\Support\Ocr\PageLayoutAnalyzer;
use App\Support\Ocr\PdfPageRasterizer;
use App\Support\Ocr\PopplerPdfPageRasterizer;
use App\Support\Ocr\TesseractOcrEngine;
use App\Support\Ocr\TesseractPageLayoutAnalyzer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OcrEngine::class, TesseractOcrEngine::class);
        $this->app->bind(PdfPageRasterizer::class, PopplerPdfPageRasterizer::class);
        $this->app->bind(PageLayoutAnalyzer::class, TesseractPageLayoutAnalyzer::class);
        $this->app->bind(NonTextRegionDetector::class, GdNonTextRegionDetector::class);
        $this->app->bind(CorrectionMarkDetector::class, GdCorrectionMarkDetector::class);
        // Off unless config/ocr.php enables one: handwriting is then transcribed by hand.
        $this->app->bind(HandwritingOcrProvider::class, fn () => HandwritingProviderFactory::make((array) config('ocr.handwriting')));
        // Off unless config/ocr.php enables a provider; see OcrCorrectionService.
        $this->app->bind(OcrCorrectionProvider::class, fn () => CorrectionProviderFactory::make((array) config('ocr.correction')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBlueprintMacros();
        $this->registerRateLimiters();
    }

    /**
     * Adds the reusable group of partial-date columns shared by F1–F9:
     * {p}_date_display, {p}_year_from/to, {p}_calendar, {p}_certainty.
     */
    private function registerBlueprintMacros(): void
    {
        Blueprint::macro('partialDate', function (string $prefix): void {
            /** @var Blueprint $this */
            $this->string("{$prefix}_date_display", 100)->nullable();
            $this->smallInteger("{$prefix}_year_from")->nullable();
            $this->smallInteger("{$prefix}_year_to")->nullable();
            $this->string("{$prefix}_calendar", 20)->default('gregorian');
            $this->string("{$prefix}_certainty", 20)->default('unknown');
        });
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => $this->app->runningUnitTests()
            ? Limit::none()
            : Limit::perMinute(120)->by($request->ip()));

        // D154: a starting number, deliberately active in tests so the limit itself can be tested.
        RateLimiter::for('submissions', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
