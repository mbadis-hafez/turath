<?php

namespace App\Support\Ocr\Correction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API. Structured output is enforced by forcing a single
 * tool call whose input_schema is the correction schema — the tool input is
 * the answer. Always external: text sent here leaves our infrastructure.
 */
class AnthropicCorrectionProvider implements OcrCorrectionProvider
{
    private const TOOL = 'record_ocr_correction';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.anthropic.com',
        private readonly int $maxTokens = 4096,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isExternal(): bool
    {
        return true;
    }

    public function correct(ProviderRequest $request): ProviderResponse
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $request->system,
            'messages' => [['role' => 'user', 'content' => $request->user]],
            'tools' => [[
                'name' => self::TOOL,
                'description' => 'Record the corrected OCR text and every change made to it.',
                'input_schema' => $request->schema,
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
        ];
        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        try {
            $response = Http::withHeaders(['x-api-key' => $this->apiKey, 'anthropic-version' => self::API_VERSION])
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->post(rtrim($this->baseUrl, '/').'/v1/messages', $body);
        } catch (ConnectionException $e) {
            throw new OcrCorrectionException('anthropic correction request could not connect: '.$e->getMessage(), true, $e);
        }

        if ($response->failed()) {
            throw OcrCorrectionException::fromStatus('anthropic', $response->status(), $response->body());
        }

        $raw = (array) ($response->json() ?? []);
        $stopReason = $raw['stop_reason'] ?? null;

        $payload = null;
        foreach ($raw['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === self::TOOL && is_array($block['input'] ?? null)) {
                $payload = $block['input'];
            }
        }

        $problem = match (true) {
            $stopReason === 'max_tokens' => 'truncated',
            $stopReason === 'refusal' => 'refused',
            $payload === null => 'missing_output',
            default => null,
        };

        return new ProviderResponse(
            payload: $problem === null ? $payload : null,
            raw: $raw,
            modelVersion: is_string($raw['model'] ?? null) ? $raw['model'] : null,
            inputTokens: isset($raw['usage']['input_tokens']) ? (int) $raw['usage']['input_tokens'] : null,
            outputTokens: isset($raw['usage']['output_tokens']) ? (int) $raw['usage']['output_tokens'] : null,
            problem: $problem,
        );
    }
}
