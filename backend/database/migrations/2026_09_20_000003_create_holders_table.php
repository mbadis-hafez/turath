<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holders', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_code', 20)->nullable()->unique();
            $table->string('type', 20);
            $table->string('name_ar', 255)->nullable();
            $table->string('name_en', 255)->nullable();
            $table->string('city_ar', 255)->nullable();
            $table->string('city_en', 255)->nullable();
            $table->string('country_ar', 255)->nullable()->default('المملكة العربية السعودية');
            $table->string('country_en', 255)->nullable()->default('Saudi Arabia');
            $table->boolean('is_public_name')->default(false);
            $table->boolean('is_estate')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

        DB::statement('ALTER TABLE holders ADD CONSTRAINT holders_name_required_check CHECK (name_ar IS NOT NULL OR name_en IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('holders');
    }
};
