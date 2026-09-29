<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_queue_items', function (Blueprint $table) {
            // FR-005: the reviewer's note on a standalone (non-proposal) outcome.
            $table->text('review_note')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('review_queue_items', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
