<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name_ar', 80)->nullable();
            $table->string('name_en', 80)->nullable();
            $table->string('description_ar', 255)->nullable();
            $table->string('description_en', 255)->nullable();
            $table->boolean('is_built_in')->default(false);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label_ar', 160)->nullable();
            $table->string('label_en', 160)->nullable();
            $table->string('group', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['label_ar', 'label_en', 'group']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_en', 'description_ar', 'description_en', 'is_built_in']);
        });
    }
};
