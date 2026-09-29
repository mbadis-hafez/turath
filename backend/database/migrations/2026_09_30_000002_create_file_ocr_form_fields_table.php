<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_ocr_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->string('field_label', 255);
            $table->foreignId('label_region_id')->nullable()->constrained('file_ocr_regions')->nullOnDelete();
            $table->foreignId('value_region_id')->nullable()->constrained('file_ocr_regions')->nullOnDelete();
            $table->string('value_type', 20)->nullable();
            $table->text('machine_value')->nullable();
            $table->boolean('requires_manual_transcription')->default(true);
            $table->text('manual_value')->nullable();
            $table->foreignId('transcribed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transcribed_at')->nullable();
            $table->timestamps();

            $table->index('file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_ocr_form_fields');
    }
};
