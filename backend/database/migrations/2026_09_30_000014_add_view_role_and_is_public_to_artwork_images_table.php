<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artwork_images', function (Blueprint $table) {
            $table->string('view_role', 30)->nullable()->after('is_final');
            $table->boolean('is_public')->default(true)->after('view_role');
        });
    }

    public function down(): void
    {
        Schema::table('artwork_images', function (Blueprint $table) {
            $table->dropColumn(['view_role', 'is_public']);
        });
    }
};
