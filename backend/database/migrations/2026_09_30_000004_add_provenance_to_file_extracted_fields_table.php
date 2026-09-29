<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('source_page')->constrained('file_ocr_regions')->nullOnDelete();
            $table->string('extraction_method', 30)->default('ocr_derived')->after('region_id');
            $table->text('original_ocr_text')->nullable()->after('extraction_method');
            $table->string('crop_path', 500)->nullable()->after('original_ocr_text');
            $table->string('ai_model', 100)->nullable()->after('crop_path');
            $table->string('ai_model_version', 50)->nullable()->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['extraction_method', 'original_ocr_text', 'crop_path', 'ai_model', 'ai_model_version']);
        });
    }
};
