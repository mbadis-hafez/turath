<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_ocr_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('region_type', 20);
            $table->string('language', 5)->nullable();
            $table->json('bbox');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->text('source_text')->nullable();
            $table->boolean('ocr_allowed')->default(false);
            $table->boolean('ai_correction_allowed')->default(false);
            $table->boolean('requires_human_review')->default(false);
            $table->string('review_reason', 255)->nullable();
            $table->string('crop_path', 500)->nullable();
            $table->timestamps();

            $table->index(['file_id', 'page_number']);
            $table->index('region_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_ocr_regions');
    }
};
