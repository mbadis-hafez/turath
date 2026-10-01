<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI-corrected layer for one region, beside — never replacing — the
     * region's OCR text (file_ocr_regions.source_text). A region can have
     * several over time (a new model or prompt version adds a row); nothing
     * here is ever treated as verified.
     */
    public function up(): void
    {
        Schema::create('file_ocr_region_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('file_ocr_regions')->cascadeOnDelete();
            $table->foreignId('ocr_correction_id')->constrained('ocr_corrections')->restrictOnDelete();
            // unchanged | corrected | needs_review | rejected
            $table->string('status', 20);
            $table->longText('corrected_text')->nullable();
            $table->boolean('needs_review');
            $table->json('review_reasons')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'ocr_correction_id']);
            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_ocr_region_corrections');
    }
};
