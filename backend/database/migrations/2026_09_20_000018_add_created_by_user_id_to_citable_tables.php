<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F10's dashboard is scoped to "my records" (D52), which needs to know who
 * created a record — a signal F1-F3 never captured. Added here rather than
 * amending the F1-F3 migrations directly, since those already shipped;
 * existing rows backfill to null ("system"), same as a null LogsChanges
 * causer.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['artists', 'artworks', 'archive_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('created_by_user_id')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
                $blueprint->index('created_by_user_id', "{$table}_created_by_user_id_index");
            });
        }
    }

    public function down(): void
    {
        foreach (['artists', 'artworks', 'archive_items'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('created_by_user_id');
            });
        }
    }
};
