<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Previously only who submitted an item was recorded, not who resolved it.
        Schema::table('review_queue_items', function (Blueprint $table) {
            $table->foreignId('acted_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable()->after('acted_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('review_queue_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acted_by_user_id');
            $table->dropColumn('acted_at');
        });
    }
};
