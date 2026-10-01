<?php

namespace App\Support\Ocr\Correction;

final readonly class ProviderRequest
{
    /**
     * @param  array<string, mixed>  $schema  JSON schema the structured output must satisfy
     */
    public function __construct(
        public string $system,
        public string $user,
        public array $schema,
        public string $schemaName,
        public ?float $temperature,
    ) {}
}
