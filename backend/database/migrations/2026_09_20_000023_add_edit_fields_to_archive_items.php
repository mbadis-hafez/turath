<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_items', function (Blueprint $table) {
            $table->string('place_ar', 255)->nullable();
            $table->string('place_en', 255)->nullable();
            $table->json('people_names')->nullable();
            $table->json('keywords')->nullable();
            $table->string('source_name', 255)->nullable();
            $table->string('verification_reference', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('archive_items', function (Blueprint $table) {
            $table->dropColumn(['place_ar', 'place_en', 'people_names', 'keywords', 'source_name', 'verification_reference']);
        });
    }
};
