<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per completed AI correction call, doubling as the cache: the
     * same masked text, provider, model, prompt version and rules version is
     * never sent twice. Text here is always the *masked* form (emails, links
     * and numbers replaced by placeholders), so this table holds no contact
     * data; region-level rows restore it for their own region.
     */
    public function up(): void
    {
        Schema::create('ocr_corrections', function (Blueprint $table) {
            $table->id();
            $table->char('input_hash', 64);
            $table->string('provider', 60);
            $table->string('model', 120);
            $table->string('model_version', 120)->nullable();
            $table->string('prompt_version', 40);
            $table->string('rules_version', 40);
            $table->string('language', 5)->nullable();
            $table->unsignedInteger('input_chars');
            // valid | invalid_output
            $table->string('status', 20);
            $table->longText('corrected_text')->nullable();
            $table->json('changes')->nullable();
            $table->json('name_candidates')->nullable();
            $table->decimal('model_confidence', 4, 3)->nullable();
            $table->boolean('model_needs_review')->default(true);
            $table->text('model_reason')->nullable();
            $table->json('guard_flags')->nullable();
            $table->json('raw_response')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['input_hash', 'provider', 'model', 'prompt_version', 'rules_version'], 'ocr_corrections_cache_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_corrections');
    }
};
