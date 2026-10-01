<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Processing pipeline
    |--------------------------------------------------------------------------
    |
    | recognize → extract → match → correct, each a separate queued job — see
    | App\Support\Ocr\Pipeline\OcrPipeline. Queue names left empty use the
    | connection's default queue, so an existing worker keeps picking OCR up.
    | AI correction can be given its own queue (and worker) so a slow or
    | rate-limited provider never delays OCR of new uploads.
    |
    | The queue connection's retry_after must be longer than the longest stage
    | timeout below, or a still-running stage is handed to a second worker
    | (which then finds it running and does nothing, but the job is recorded
    | as failed by the queue).
    |
    */

    'pipeline' => [
        'queue' => env('OCR_QUEUE') ?: null,
        'ai_queue' => env('OCR_AI_QUEUE') ?: null,

        'timeouts' => [
            'recognize' => (int) env('OCR_RECOGNIZE_TIMEOUT', 1800),
            'extract' => (int) env('OCR_EXTRACT_TIMEOUT', 120),
            'match' => (int) env('OCR_MATCH_TIMEOUT', 300),
            'correct' => (int) env('OCR_CORRECT_TIMEOUT', 1800),
        ],

        // A stage queued this long without starting is treated as lost (e.g. a flushed queue), so it can be run again.
        'queued_stale_after_minutes' => (int) env('OCR_QUEUED_STALE_AFTER_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI OCR correction
    |--------------------------------------------------------------------------
    |
    | Off by default. When enabled, only printed-text OCR regions (never
    | handwriting, signatures, logos, photographs or noise) are sent, with
    | emails, links and numbers masked first. A correction is always stored
    | as a separate layer beside the OCR text and is never trusted without
    | human review — see App\Support\Ocr\Correction\OcrCorrectionService.
    |
    | External providers (anything that sends text off infrastructure we
    | control) need an explicit opt-in, and even then only receive material
    | at or below `external_max_access_level`. Saudi data-residency rules
    | apply to archive material: check before enabling an external provider.
    |
    */

    'correction' => [
        'enabled' => (bool) env('OCR_CORRECTION_ENABLED', false),

        // none | anthropic | openai_compatible
        'provider' => env('OCR_CORRECTION_PROVIDER', 'none'),

        'allow_external_providers' => (bool) env('OCR_CORRECTION_ALLOW_EXTERNAL', false),

        // public | registered | researcher | institution_only — embargoed material is never sent externally.
        'external_max_access_level' => env('OCR_CORRECTION_EXTERNAL_MAX_ACCESS_LEVEL', 'public'),

        // Below this self-reported model confidence a correction always goes to review. It's the
        // model's own estimate, not a calibrated probability, so it can only ever add review.
        'review_below_confidence' => (float) env('OCR_CORRECTION_REVIEW_BELOW_CONFIDENCE', 0.85),

        'max_region_chars' => (int) env('OCR_CORRECTION_MAX_REGION_CHARS', 4000),

        'timeout_seconds' => (int) env('OCR_CORRECTION_TIMEOUT', 60),

        // How long a worker waits for another worker already correcting the same text, before retrying later.
        'lock_wait_seconds' => (int) env('OCR_CORRECTION_LOCK_WAIT', 90),

        // Omit (empty) for models that reject a temperature parameter.
        'temperature' => env('OCR_CORRECTION_TEMPERATURE', '0') === '' ? null : (float) env('OCR_CORRECTION_TEMPERATURE', '0'),

        'providers' => [
            'anthropic' => [
                'api_key' => env('ANTHROPIC_API_KEY'),
                'base_url' => env('OCR_CORRECTION_ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
                'model' => env('OCR_CORRECTION_ANTHROPIC_MODEL', 'claude-sonnet-5'),
                'max_tokens' => (int) env('OCR_CORRECTION_ANTHROPIC_MAX_TOKENS', 4096),
            ],

            // OpenAI, Google Gemini (its OpenAI-compatible endpoint), or a local server such as
            // Ollama or vLLM. Set `external` to false only for a server on infrastructure we control.
            'openai_compatible' => [
                'label' => env('OCR_CORRECTION_OPENAI_LABEL', 'openai_compatible'),
                'api_key' => env('OCR_CORRECTION_OPENAI_API_KEY'),
                'base_url' => env('OCR_CORRECTION_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'model' => env('OCR_CORRECTION_OPENAI_MODEL'),
                'external' => (bool) env('OCR_CORRECTION_OPENAI_EXTERNAL', true),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handwriting suggestions
    |--------------------------------------------------------------------------
    |
    | Optional. With no provider, handwriting is transcribed by hand, exactly
    | as before. With one, a reviewer can ask for a machine *suggestion* for a
    | handwriting crop; it is never a verified value until a reviewer submits
    | it. Suggestions are only ever made on request, never in bulk by the
    | OCR job, since every crop sent is a real person's handwriting.
    |
    | The same external-provider rules as correction apply: an image sent to
    | a cloud service leaves our infrastructure, and handwriting on these
    | forms is mostly names, phone numbers and addresses.
    |
    */

    'handwriting' => [
        'enabled' => (bool) env('OCR_HANDWRITING_ENABLED', false),

        // none | kraken | azure
        'provider' => env('OCR_HANDWRITING_PROVIDER', 'none'),

        'allow_external_providers' => (bool) env('OCR_HANDWRITING_ALLOW_EXTERNAL', false),

        'external_max_access_level' => env('OCR_HANDWRITING_EXTERNAL_MAX_ACCESS_LEVEL', 'public'),

        'providers' => [
            // Self-hosted. Verified against kraken 7.1.1 with the Muharaf recognition model
            // (Zenodo 10.5281/zenodo.14295489, CC-BY-4.0): Arabic script only.
            'kraken' => [
                'binary' => env('OCR_HANDWRITING_KRAKEN_BINARY', 'kraken'),
                'model' => env('OCR_HANDWRITING_KRAKEN_MODEL'),
                'base_dir' => env('OCR_HANDWRITING_KRAKEN_BASE_DIR', 'R'),
                'timeout_seconds' => (int) env('OCR_HANDWRITING_KRAKEN_TIMEOUT', 120),
            ],

            // Azure Document Intelligence prebuilt-read, API v4.0 (2024-11-30), whose handwriting
            // support includes Arabic. External: images leave our infrastructure.
            'azure' => [
                'endpoint' => env('OCR_HANDWRITING_AZURE_ENDPOINT'),
                'key' => env('OCR_HANDWRITING_AZURE_KEY'),
                'api_version' => env('OCR_HANDWRITING_AZURE_API_VERSION', '2024-11-30'),
                'model' => env('OCR_HANDWRITING_AZURE_MODEL', 'prebuilt-read'),
                'poll_interval_ms' => (int) env('OCR_HANDWRITING_AZURE_POLL_MS', 1000),
                'max_polls' => (int) env('OCR_HANDWRITING_AZURE_MAX_POLLS', 30),
                'timeout_seconds' => (int) env('OCR_HANDWRITING_AZURE_TIMEOUT', 60),
            ],
        ],
    ],

];
