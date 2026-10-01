<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer decisions collected as evaluation and (later) training data.
     * Nothing here is fine-tuning: it is the record of what the machine read,
     * what the AI suggested and what a person decided, kept so each stage can
     * be measured and, one day, improved.
     *
     * - ocr_review_examples: one row per reviewer decision, appended (a later
     *   decision on the same thing adds a row; the latest one counts). Text is
     *   copied at decision time with contact-like tokens redacted, because
     *   re-running OCR replaces the rows it came from. Contact details are
     *   never recorded at all.
     * - ocr_dataset_documents: which split each archive item's examples belong
     *   to — training, evaluation or excluded. Assigned once and locked when
     *   first exported, so a document can never move between training and
     *   evaluation after a model may have seen it.
     * - ocr_evaluation_reserved_files: file checksums (benchmark documents)
     *   that may only ever be evaluation data, wherever they get uploaded.
     * - ocr_dataset_exports: an audit of every export written.
     */
    public function up(): void
    {
        Schema::create('ocr_dataset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_item_id')->unique()->constrained()->cascadeOnDelete();
            // training | evaluation | excluded
            $table->string('split', 20);
            // automatic | reviewer | reserved
            $table->string('assigned_by', 20);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index('split');
        });

        Schema::create('ocr_evaluation_reserved_files', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64)->unique();
            $table->string('source', 40);
            $table->timestamps();
        });

        Schema::create('ocr_review_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained()->nullOnDelete();
            // field | date | form_field | handwriting | match
            $table->string('kind', 20);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('document_type', 40)->nullable();
            $table->string('language', 5)->nullable();
            // field key, date role or entity type
            $table->string('key', 100)->nullable();
            $table->longText('ocr_text')->nullable();
            $table->longText('ai_correction')->nullable();
            $table->longText('machine_suggestion')->nullable();
            $table->longText('human_correction')->nullable();
            $table->boolean('accepted')->nullable();
            $table->string('reviewer_action', 30);
            $table->string('extraction_method', 30)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            // {status, needs_review, review_reasons, provider, model, model_version, prompt_version}
            $table->json('ai_meta')->nullable();
            // {candidates: [{id, strength, score}], top_id, top_strength, confirmed_id}
            $table->json('match')->nullable();
            $table->boolean('redacted')->default(false);
            $table->string('recorder_version', 20);
            // Internal only — never exported.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['kind', 'decided_at']);
        });

        Schema::create('ocr_dataset_exports', function (Blueprint $table) {
            $table->id();
            $table->string('split', 20);
            $table->unsignedInteger('example_count');
            $table->unsignedInteger('document_count');
            $table->string('path', 500);
            $table->char('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_dataset_exports');
        Schema::dropIfExists('ocr_review_examples');
        Schema::dropIfExists('ocr_evaluation_reserved_files');
        Schema::dropIfExists('ocr_dataset_documents');
    }
};
