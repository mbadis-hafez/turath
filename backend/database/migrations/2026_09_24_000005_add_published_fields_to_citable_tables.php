<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors artists.verified_by_user_id/verified_at — who published and when,
        // queryable directly on the record instead of only via the activity log.
        // Table names are spelled out literally (not looped) so larastan's static
        // migration reflection can attribute the columns to each model.
        Schema::table('artists', function (Blueprint $table) {
            $table->foreignId('published_by_user_id')->nullable()->after('publication_status')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by_user_id');
        });
        Schema::table('artworks', function (Blueprint $table) {
            $table->foreignId('published_by_user_id')->nullable()->after('publication_status')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by_user_id');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('published_by_user_id')->nullable()->after('publication_status')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by_user_id');
        });
        Schema::table('archive_items', function (Blueprint $table) {
            $table->foreignId('published_by_user_id')->nullable()->after('publication_status')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('published_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn('published_at');
        });
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn('published_at');
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn('published_at');
        });
        Schema::table('archive_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_user_id');
            $table->dropColumn('published_at');
        });
    }
};
