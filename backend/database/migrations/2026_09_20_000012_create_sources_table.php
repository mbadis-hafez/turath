<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type', 30);
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('publisher_or_outlet', 255)->nullable();
            $table->string('reference_note', 255)->nullable();
            $table->string('url', 500)->nullable();
            $table->smallInteger('year')->nullable();
            $table->foreignId('added_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
