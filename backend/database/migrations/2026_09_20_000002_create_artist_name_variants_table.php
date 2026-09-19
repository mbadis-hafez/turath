<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_name_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('language', 3)->default('und');
            $table->string('type', 20);
            $table->string('source_note', 255)->nullable();
            $table->timestamps();

            $table->unique(['artist_id', 'name'], 'artist_name_variants_artist_name_unique');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_name_variants');
    }
};
