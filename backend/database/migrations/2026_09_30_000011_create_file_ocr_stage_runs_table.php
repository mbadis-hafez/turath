<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The state of each OCR pipeline stage for a file — one row per stage,
     * updated in place. input_fingerprint is set only when a run succeeds and
     * cleared when a run starts, so a matching fingerprint means that stage's
     * stored output was produced from exactly the current input and the stage
     * need not run again. run_id ties a queued job to the pipeline run that
     * dispatched it; a job whose run has been superseded does nothing.
     */
    public function up(): void
    {
        Schema::create('file_ocr_stage_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32);
            $table->string('status', 16);
            $table->string('reason', 64)->nullable();
            $table->uuid('run_id')->nullable();
            $table->char('input_fingerprint', 64)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'stage']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_ocr_stage_runs');
    }
};
