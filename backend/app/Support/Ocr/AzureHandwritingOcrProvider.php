<?php

namespace App\Support\Ocr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Azure AI Document Intelligence, prebuilt-read model, REST API v4.0
 * (2024-11-30, GA). Microsoft's language table for that version lists Arabic
 * among the languages supported for *handwritten* text in the Read model (it
 * was not in v3.1). Analysis is asynchronous: submit, then poll the
 * Operation-Location URL until it succeeds or fails.
 *
 * External: the crop leaves our infrastructure. As of 2026-09-30 Microsoft
 * has announced a Saudi Arabia East region for November 2026; whether
 * Document Intelligence is offered there was not confirmed. Not exercised
 * against a live endpoint in this codebase — the request/response handling
 * follows the documented API and is tested against recorded shapes only.
 */
class AzureHandwritingOcrProvider implements HandwritingOcrProvider
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $key,
        private readonly string $modelId = 'prebuilt-read',
        private readonly string $apiVersion = '2024-11-30',
        private readonly int $pollIntervalMs = 1000,
        private readonly int $maxPolls = 30,
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function name(): string
    {
        return 'azure';
    }

    public function model(): string
    {
        return $this->modelId;
    }

    public function isExternal(): bool
    {
        return true;
    }

    public function suggest(string $cropImagePath, ?string $language): HandwritingSuggestion
    {
        $bytes = @file_get_contents($cropImagePath);
        if ($bytes === false) {
            throw new HandwritingOcrException("Could not read crop image {$cropImagePath}.", false);
        }

        // No locale: Microsoft advises against forcing one unless certain, and handwriting regions rarely are.
        $url = rtrim($this->endpoint, '/')."/documentintelligence/documentModels/{$this->modelId}:analyze?api-version={$this->apiVersion}";
        $submitted = $this->send(fn () => $this->http()->post($url, ['base64Source' => base64_encode($bytes)]));
        if ($submitted->status() !== 202) {
            throw $this->failure($submitted);
        }

        $operation = $submitted->header('Operation-Location');
        if ($operation === '') {
            throw new HandwritingOcrException('azure accepted the request but returned no Operation-Location.', true);
        }

        for ($poll = 0; $poll < $this->maxPolls; $poll++) {
            Sleep::for($this->pollIntervalMs)->milliseconds();

            $response = $this->send(fn () => $this->http()->get($operation));
            if ($response->failed()) {
                throw $this->failure($response);
            }

            $body = (array) ($response->json() ?? []);
            $status = $body['status'] ?? null;
            if ($status === 'succeeded') {
                return $this->toSuggestion((array) ($body['analyzeResult'] ?? []));
            }
            if ($status === 'failed') {
                $error = $body['error']['message'] ?? 'unknown error';

                throw new HandwritingOcrException("azure analysis failed: {$error}", false);
            }
        }

        throw new HandwritingOcrException("azure analysis did not finish after {$this->maxPolls} polls.", true);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toSuggestion(array $result): HandwritingSuggestion
    {
        $words = [];
        foreach ($result['pages'] ?? [] as $page) {
            foreach ($page['words'] ?? [] as $word) {
                $words[] = ['text' => (string) ($word['content'] ?? ''), 'confidence' => isset($word['confidence']) ? (float) $word['confidence'] : null];
            }
        }
        $confidences = array_values(array_filter(array_column($words, 'confidence'), fn ($c) => $c !== null));

        return new HandwritingSuggestion(
            text: trim((string) ($result['content'] ?? '')),
            confidence: $confidences === [] ? null : round(array_sum($confidences) / count($confidences), 3),
            modelVersion: isset($result['modelId']) ? $result['modelId'].'@'.($result['apiVersion'] ?? $this->apiVersion) : null,
            // Polygons dropped: the crop is stored, and they would only bloat the audit row.
            raw: [
                'engine' => 'azure',
                'content' => $result['content'] ?? null,
                'words' => $words,
                'styles' => array_map(fn ($s) => ['is_handwritten' => $s['isHandwritten'] ?? null, 'confidence' => $s['confidence'] ?? null], $result['styles'] ?? []),
                'languages' => $result['languages'] ?? [],
            ],
        );
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['Ocp-Apim-Subscription-Key' => $this->key])->acceptJson()->timeout($this->timeoutSeconds);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new HandwritingOcrException('azure request could not connect: '.$e->getMessage(), true, $e);
        }
    }

    private function failure(Response $response): HandwritingOcrException
    {
        $status = $response->status();

        return new HandwritingOcrException(
            "azure request failed with HTTP {$status}: ".mb_substr($response->body(), 0, 500),
            $status === 408 || $status === 429 || $status >= 500,
        );
    }
}
