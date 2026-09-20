<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artwork_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained('artworks')->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->string('rights_status', 20)->default('unknown');
            $table->boolean('is_final')->default(false);
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['artwork_id', 'is_final']);
            $table->index('sha256');
        });

        // Images now live in artwork_images (the files table is tied to archive items).
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('final_selected_hr_image_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('artworks', function (Blueprint $table) {
            $table->foreignId('final_selected_hr_image_file_id')->nullable()->constrained('files')->nullOnDelete();
        });
        Schema::dropIfExists('artwork_images');
    }
};
