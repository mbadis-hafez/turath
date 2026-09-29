<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_extracted_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->string('language', 5);
            $table->longText('text');
            $table->unsignedTinyInteger('confidence');
            $table->json('segments')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'page_number', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_extracted_texts');
    }
};
