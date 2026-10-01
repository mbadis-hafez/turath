<?php

namespace App\Support\Ocr\Correction;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Any server speaking the OpenAI chat-completions protocol with JSON-schema
 * structured output: OpenAI itself, Google Gemini through its
 * OpenAI-compatible endpoint, or a local server such as Ollama or vLLM. How
 * strictly a given server enforces the schema varies, which is why the
 * output is validated locally by CorrectionGuard regardless.
 *
 * Whether it is external is configuration, not inference from the URL: only
 * a server on infrastructure we control should be configured as local.
 */
class OpenAiCompatibleCorrectionProvider implements OcrCorrectionProvider
{
    public function __construct(
        private readonly string $label,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly bool $external = true,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function name(): string
    {
        return $this->label;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function correct(ProviderRequest $request): ProviderResponse
    {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user', 'content' => $request->user],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => $request->schemaName, 'strict' => true, 'schema' => $request->schema],
            ],
        ];
        if ($request->temperature !== null) {
            $body['temperature'] = $request->temperature;
        }

        $http = Http::acceptJson()->timeout($this->timeoutSeconds);
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $http = $http->withToken($this->apiKey);
        }

        try {
            $response = $http->post(rtrim($this->baseUrl, '/').'/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw new OcrCorrectionException("{$this->label} correction request could not connect: ".$e->getMessage(), true, $e);
        }

        if ($response->failed()) {
            throw OcrCorrectionException::fromStatus($this->label, $response->status(), $response->body());
        }

        $raw = (array) ($response->json() ?? []);
        $choice = $raw['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? null;
        $decoded = is_string($content) ? json_decode($content, true) : null;
        $payload = is_array($decoded) ? $decoded : null;

        $problem = match (true) {
            ($choice['finish_reason'] ?? null) === 'length' => 'truncated',
            is_string($message['refusal'] ?? null) && $message['refusal'] !== '' => 'refused',
            ! is_string($content) || $content === '' => 'missing_output',
            $payload === null => 'unparseable',
            default => null,
        };

        return new ProviderResponse(
            payload: $problem === null ? $payload : null,
            raw: $raw,
            modelVersion: is_string($raw['model'] ?? null) ? $raw['model'] : null,
            inputTokens: isset($raw['usage']['prompt_tokens']) ? (int) $raw['usage']['prompt_tokens'] : null,
            outputTokens: isset($raw['usage']['completion_tokens']) ? (int) $raw['usage']['completion_tokens'] : null,
            problem: $problem,
        );
    }
}
