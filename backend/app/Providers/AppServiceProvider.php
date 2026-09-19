<?php

namespace App\Providers;

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
        //
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

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
