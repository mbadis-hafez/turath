<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_extracted_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('field_key', 60);
            $table->text('extracted_value')->nullable();
            $table->unsignedTinyInteger('confidence');
            $table->unsignedInteger('source_page')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'field_key']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_extracted_fields');
    }
};
