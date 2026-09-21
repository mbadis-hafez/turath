<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artist_entries', function (Blueprint $table) {
            // Intentionally not a foreign key: the events table is dropped and
            // re-created by an older data migration, and MySQL refuses to drop a
            // table referenced by a constraint. The link is machine-maintained
            // (ArtistActivityEventSync) and events are soft-deleted.
            $table->unsignedBigInteger('event_id')->nullable()->after('type');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('artist_entries', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
