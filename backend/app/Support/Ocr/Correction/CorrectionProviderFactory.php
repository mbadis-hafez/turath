<?php

namespace App\Support\Ocr\Correction;

use InvalidArgumentException;

/**
 * Builds the configured provider from config('ocr.correction'). Disabled or
 * "none" gives the null provider; a selected provider with missing settings
 * fails loudly rather than silently falling back to no correction.
 */
class CorrectionProviderFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): OcrCorrectionProvider
    {
        if (! ($config['enabled'] ?? false)) {
            return new NullCorrectionProvider;
        }

        $timeout = (int) ($config['timeout_seconds'] ?? 60);

        return match ($config['provider'] ?? NullCorrectionProvider::NAME) {
            NullCorrectionProvider::NAME => new NullCorrectionProvider,
            'anthropic' => new AnthropicCorrectionProvider(
                apiKey: self::required($config, 'anthropic', 'api_key', 'ANTHROPIC_API_KEY'),
                model: self::required($config, 'anthropic', 'model', 'OCR_CORRECTION_ANTHROPIC_MODEL'),
                baseUrl: (string) ($config['providers']['anthropic']['base_url'] ?? 'https://api.anthropic.com'),
                maxTokens: (int) ($config['providers']['anthropic']['max_tokens'] ?? 4096),
                timeoutSeconds: $timeout,
            ),
            'openai_compatible' => new OpenAiCompatibleCorrectionProvider(
                label: (string) ($config['providers']['openai_compatible']['label'] ?? 'openai_compatible'),
                apiKey: $config['providers']['openai_compatible']['api_key'] ?? null,
                model: self::required($config, 'openai_compatible', 'model', 'OCR_CORRECTION_OPENAI_MODEL'),
                baseUrl: self::required($config, 'openai_compatible', 'base_url', 'OCR_CORRECTION_OPENAI_BASE_URL'),
                external: (bool) ($config['providers']['openai_compatible']['external'] ?? true),
                timeoutSeconds: $timeout,
            ),
            default => throw new InvalidArgumentException('Unknown OCR correction provider "'.$config['provider'].'".'),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function required(array $config, string $provider, string $key, string $env): string
    {
        $value = $config['providers'][$provider][$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("OCR correction provider \"{$provider}\" is selected but {$env} is not set.");
        }

        return $value;
    }
}
