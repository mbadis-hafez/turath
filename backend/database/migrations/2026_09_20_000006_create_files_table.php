<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_item_id')->constrained('archive_items')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('archive_item_id');
            $table->index('sha256');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
