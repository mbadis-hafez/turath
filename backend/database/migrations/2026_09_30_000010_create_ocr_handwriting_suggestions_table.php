<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A machine suggestion for a handwriting crop, and what a reviewer
     * decided about it. Keyed by the crop's content (sha256), not by region,
     * so re-running OCR — which recreates regions but reproduces identical
     * crops — neither pays for a second suggestion nor loses the decision.
     * The suggestion is never itself a verified value; final_text is what a
     * reviewer submitted.
     */
    public function up(): void
    {
        Schema::create('ocr_handwriting_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('file_ocr_regions')->nullOnDelete();
            $table->string('crop_path', 500);
            $table->char('crop_sha256', 64);
            $table->string('provider', 60);
            $table->string('model', 190);
            $table->string('model_version', 120)->nullable();
            $table->string('language', 5)->nullable();
            // suggested | empty (the provider ran and read nothing)
            $table->string('status', 20);
            $table->text('text')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('raw_result')->nullable();
            // accepted | edited | rejected — null until a reviewer decides
            $table->string('decision', 20)->nullable();
            $table->text('final_text')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['file_id', 'crop_sha256', 'provider', 'model'], 'ocr_handwriting_suggestions_crop_key');
        });

        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->char('crop_sha256', 64)->nullable()->after('crop_path');
        });
    }

    public function down(): void
    {
        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->dropColumn('crop_sha256');
        });

        Schema::dropIfExists('ocr_handwriting_suggestions');
    }
};
