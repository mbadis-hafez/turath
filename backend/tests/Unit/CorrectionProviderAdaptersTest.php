<?php

use App\Support\Ocr\Correction\AnthropicCorrectionProvider;
use App\Support\Ocr\Correction\CorrectionPrompt;
use App\Support\Ocr\Correction\CorrectionProviderFactory;
use App\Support\Ocr\Correction\NullCorrectionProvider;
use App\Support\Ocr\Correction\OcrCorrectionException;
use App\Support\Ocr\Correction\OcrCorrectionProvider;
use App\Support\Ocr\Correction\OpenAiCompatibleCorrectionProvider;
use App\Support\Ocr\Correction\ProviderRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function adapterRequest(?float $temperature = 0.0): ProviderRequest
{
    $prompt = new CorrectionPrompt;

    return new ProviderRequest($prompt->system(), $prompt->user('المملكه ⟦N1⟧', 'ar'), $prompt->schema(), CorrectionPrompt::SCHEMA_NAME, $temperature);
}

function correctionAnswer(): array
{
    return [
        'corrected_text' => 'المملكة ⟦N1⟧',
        'changes' => [['original' => 'المملكه', 'corrected' => 'المملكة', 'type' => 'orthographic_normalization']],
        'name_candidates' => [], 'confidence' => 0.93, 'needs_review' => false, 'reason' => null,
    ];
}

function anthropicProvider(): AnthropicCorrectionProvider
{
    return new AnthropicCorrectionProvider('test-key', 'claude-sonnet-5', 'https://api.anthropic.test');
}

function openAiProvider(?string $key = 'sk-test', bool $external = true): OpenAiCompatibleCorrectionProvider
{
    return new OpenAiCompatibleCorrectionProvider('openai', $key, 'gpt-test', 'https://llm.test/v1', $external);
}

describe('Anthropic', function () {
    it('forces a single tool call whose input schema is the correction schema', function () {
        Http::fake(['api.anthropic.test/*' => Http::response([
            'model' => 'claude-sonnet-5-20260901',
            'stop_reason' => 'tool_use',
            'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'record_ocr_correction', 'input' => correctionAnswer()]],
            'usage' => ['input_tokens' => 812, 'output_tokens' => 64],
        ])]);

        $response = anthropicProvider()->correct(adapterRequest());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.anthropic.test/v1/messages'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request['model'] === 'claude-sonnet-5'
                && $request['tool_choice'] === ['type' => 'tool', 'name' => 'record_ocr_correction']
                && $request['tools'][0]['input_schema'] === (new CorrectionPrompt)->schema()
                && $request['temperature'] === 0.0
                && str_contains($request['system'], 'Proper names');
        });
        expect($response->payload)->toBe(correctionAnswer())
            ->and($response->modelVersion)->toBe('claude-sonnet-5-20260901')
            ->and($response->inputTokens)->toBe(812)
            ->and($response->outputTokens)->toBe(64)
            ->and($response->problem)->toBeNull();
    });

    it('omits temperature when configured off', function () {
        Http::fake(['*' => Http::response(['content' => [], 'stop_reason' => 'end_turn'])]);

        anthropicProvider()->correct(adapterRequest(null));

        Http::assertSent(fn (Request $request) => ! array_key_exists('temperature', $request->data()));
    });

    it('returns no payload when the answer was truncated, refused or missing', function (array $body, string $problem) {
        Http::fake(['*' => Http::response($body)]);

        $response = anthropicProvider()->correct(adapterRequest());

        expect($response->payload)->toBeNull()->and($response->problem)->toBe($problem);
    })->with([
        'truncated' => [['stop_reason' => 'max_tokens', 'content' => [['type' => 'tool_use', 'name' => 'record_ocr_correction', 'input' => ['corrected_text' => 'المم']]]], 'truncated'],
        'refused' => [['stop_reason' => 'refusal', 'content' => []], 'refused'],
        'no tool call' => [['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'Here is the corrected text…']]], 'missing_output'],
    ]);

    it('treats rate limits and server errors as retryable, auth failures as not', function (int $status, bool $retryable) {
        Http::fake(['*' => Http::response(['error' => ['type' => 'x']], $status)]);

        try {
            anthropicProvider()->correct(adapterRequest());
            $this->fail('Expected an exception.');
        } catch (OcrCorrectionException $e) {
            expect($e->retryable)->toBe($retryable);
        }
    })->with([[429, true], [529, true], [500, true], [401, false], [400, false]]);

    it('treats a dropped connection as retryable', function () {
        Http::fake(['*' => Http::failedConnection()]);

        expect(fn () => anthropicProvider()->correct(adapterRequest()))->toThrow(fn (OcrCorrectionException $e) => expect($e->retryable)->toBeTrue());
    });
});

describe('OpenAI-compatible', function () {
    it('requests strict JSON-schema output and parses the message content', function () {
        Http::fake(['llm.test/*' => Http::response([
            'model' => 'gpt-test-2026-08',
            'choices' => [['finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => json_encode(correctionAnswer()), 'refusal' => null]]],
            'usage' => ['prompt_tokens' => 700, 'completion_tokens' => 50],
        ])]);

        $response = openAiProvider()->correct(adapterRequest());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://llm.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test')
                && $request['response_format']['type'] === 'json_schema'
                && $request['response_format']['json_schema']['strict'] === true
                && $request['response_format']['json_schema']['schema'] === (new CorrectionPrompt)->schema()
                && $request['messages'][0]['role'] === 'system';
        });
        expect($response->payload)->toBe(correctionAnswer())
            ->and($response->modelVersion)->toBe('gpt-test-2026-08')
            ->and($response->inputTokens)->toBe(700);
    });

    it('sends no Authorization header to a local server configured without a key', function () {
        Http::fake(['*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => json_encode(correctionAnswer())]]]])]);

        $provider = openAiProvider(null, external: false);
        $provider->correct(adapterRequest());

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
        expect($provider->isExternal())->toBeFalse();
    });

    it('returns no payload for a refusal, truncation or unparseable content', function (array $choice, string $problem) {
        Http::fake(['*' => Http::response(['choices' => [$choice]])]);

        $response = openAiProvider()->correct(adapterRequest());

        expect($response->payload)->toBeNull()->and($response->problem)->toBe($problem);
    })->with([
        'refused' => [['finish_reason' => 'stop', 'message' => ['content' => null, 'refusal' => 'I can’t help with that.']], 'refused'],
        'truncated' => [['finish_reason' => 'length', 'message' => ['content' => '{"corrected_text": "المم']], 'truncated'],
        'not JSON' => [['finish_reason' => 'stop', 'message' => ['content' => 'المملكة ⟦N1⟧']], 'unparseable'],
        'empty' => [['finish_reason' => 'stop', 'message' => ['content' => '']], 'missing_output'],
    ]);

    it('treats a server error as retryable', function () {
        Http::fake(['*' => Http::response('overloaded', 503)]);

        expect(fn () => openAiProvider()->correct(adapterRequest()))->toThrow(fn (OcrCorrectionException $e) => expect($e->retryable)->toBeTrue());
    });
});

describe('factory', function () {
    it('gives no provider when correction is disabled or set to none', function () {
        expect(CorrectionProviderFactory::make(['enabled' => false, 'provider' => 'anthropic']))->toBeInstanceOf(NullCorrectionProvider::class)
            ->and(CorrectionProviderFactory::make(['enabled' => true, 'provider' => 'none']))->toBeInstanceOf(NullCorrectionProvider::class)
            ->and(app(OcrCorrectionProvider::class))->toBeInstanceOf(NullCorrectionProvider::class);
    });

    it('fails loudly when a selected provider is missing its settings, rather than silently not correcting', function () {
        expect(fn () => CorrectionProviderFactory::make(['enabled' => true, 'provider' => 'anthropic', 'providers' => ['anthropic' => ['model' => 'claude-sonnet-5']]]))
            ->toThrow(InvalidArgumentException::class, 'ANTHROPIC_API_KEY')
            ->and(fn () => CorrectionProviderFactory::make(['enabled' => true, 'provider' => 'mystery']))
            ->toThrow(InvalidArgumentException::class, 'Unknown');
    });

    it('builds a local OpenAI-compatible provider that is not external', function () {
        $provider = CorrectionProviderFactory::make(['enabled' => true, 'provider' => 'openai_compatible', 'providers' => ['openai_compatible' => [
            'label' => 'ollama', 'base_url' => 'http://127.0.0.1:11434/v1', 'model' => 'qwen-arabic', 'external' => false,
        ]]]);

        expect($provider->name())->toBe('ollama')
            ->and($provider->model())->toBe('qwen-arabic')
            ->and($provider->isExternal())->toBeFalse();
    });
});
