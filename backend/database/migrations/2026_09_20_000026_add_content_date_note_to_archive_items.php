<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_items', function (Blueprint $table) {
            $table->string('content_date_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('archive_items', function (Blueprint $table) {
            $table->dropColumn('content_date_note');
        });
    }
};
