<?php

namespace App\Support\Ocr\Correction;

final readonly class ProviderResponse
{
    /**
     * @param  array<string, mixed>|null  $payload  the decoded structured output, or null when unusable
     * @param  array<string, mixed>  $raw  the provider's full response body, kept for audit
     * @param  string|null  $problem  why the payload is null: truncated | refused | unparseable | missing_output
     */
    public function __construct(
        public ?array $payload,
        public array $raw,
        public ?string $modelVersion,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?string $problem = null,
    ) {}
}
