<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F4 introduces UUID-keyed models (ImportBatch, ImportBatchRow,
 * ImportMappingProfile) that also use LogsChanges. spatie/activitylog's
 * subject_id/causer_id columns default to unsignedBigInteger (via
 * nullableMorphs), which can't hold a UUID. Widening both to a string
 * lets the same activity_log table serve both bigint-keyed models
 * (Artist, Artwork, Holder, ArchiveItem, User) and UUID-keyed ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE activity_log MODIFY subject_id VARCHAR(36) NULL');
        DB::statement('ALTER TABLE activity_log MODIFY causer_id VARCHAR(36) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activity_log MODIFY subject_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE activity_log MODIFY causer_id BIGINT UNSIGNED NULL');
    }
};
