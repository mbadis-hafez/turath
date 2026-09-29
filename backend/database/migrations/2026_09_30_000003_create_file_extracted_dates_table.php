<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_extracted_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->string('value', 40);
            $table->string('calendar', 20)->default('unknown');
            $table->string('date_type', 40)->default('other');
            $table->unsignedInteger('source_page')->nullable();
            $table->string('source_method', 20)->default('ocr');
            $table->foreignId('region_id')->nullable()->constrained('file_ocr_regions')->nullOnDelete();
            $table->timestamps();

            $table->index(['file_id', 'date_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_extracted_dates');
    }
};
