<?php

namespace App\Support\Ocr;

use InvalidArgumentException;

/**
 * Builds the configured handwriting provider from config('ocr.handwriting').
 * Disabled or "none" gives the null provider (manual transcription only); a
 * selected provider with missing settings fails loudly.
 */
class HandwritingProviderFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): HandwritingOcrProvider
    {
        if (! ($config['enabled'] ?? false)) {
            return new NullHandwritingOcrProvider;
        }

        $kraken = (array) ($config['providers']['kraken'] ?? []);
        $azure = (array) ($config['providers']['azure'] ?? []);

        return match ($config['provider'] ?? NullHandwritingOcrProvider::NAME) {
            NullHandwritingOcrProvider::NAME => new NullHandwritingOcrProvider,
            'kraken' => new KrakenHandwritingOcrProvider(
                modelPath: self::required($kraken, 'model', 'OCR_HANDWRITING_KRAKEN_MODEL'),
                binaryPath: (string) ($kraken['binary'] ?? 'kraken'),
                baseDir: (string) ($kraken['base_dir'] ?? 'R'),
                timeoutSeconds: (int) ($kraken['timeout_seconds'] ?? 120),
            ),
            'azure' => new AzureHandwritingOcrProvider(
                endpoint: self::required($azure, 'endpoint', 'OCR_HANDWRITING_AZURE_ENDPOINT'),
                key: self::required($azure, 'key', 'OCR_HANDWRITING_AZURE_KEY'),
                modelId: (string) ($azure['model'] ?? 'prebuilt-read'),
                apiVersion: (string) ($azure['api_version'] ?? '2024-11-30'),
                pollIntervalMs: (int) ($azure['poll_interval_ms'] ?? 1000),
                maxPolls: (int) ($azure['max_polls'] ?? 30),
                timeoutSeconds: (int) ($azure['timeout_seconds'] ?? 60),
            ),
            default => throw new InvalidArgumentException('Unknown handwriting provider "'.$config['provider'].'".'),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function required(array $settings, string $key, string $env): string
    {
        $value = $settings[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("A handwriting provider is selected but {$env} is not set.");
        }

        return $value;
    }
}
