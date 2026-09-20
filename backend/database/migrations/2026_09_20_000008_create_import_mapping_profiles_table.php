<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 20);
            $table->string('name', 255);
            $table->json('column_map');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_profiles');
    }
};
