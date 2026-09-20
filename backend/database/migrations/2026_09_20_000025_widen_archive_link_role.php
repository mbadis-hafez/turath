<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `primary_documentation` (D109) is 21 characters.
        Schema::table('archive_item_links', function (Blueprint $table) {
            $table->string('role', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('archive_item_links', function (Blueprint $table) {
            $table->string('role', 20)->change();
        });
    }
};
