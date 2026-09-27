<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable until a reviewer approves a record's creation-review item (005).
 * Every row that already exists is backfilled to now() — nothing already in
 * the archive retroactively needs review; only records created after this
 * feature ships start unreviewed.
 */
return new class extends Migration
{
    private const TABLES = ['artists', 'artworks', 'events', 'archive_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('creation_approved_at')->nullable();
            });
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->update(['creation_approved_at' => now()]);
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('creation_approved_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['creation_approved_at']);
                $blueprint->dropColumn('creation_approved_at');
            });
        }
    }
};
