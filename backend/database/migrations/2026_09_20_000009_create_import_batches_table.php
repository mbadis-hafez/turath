<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 20);
            $table->foreignUuid('mapping_profile_id')->nullable()->constrained('import_mapping_profiles')->nullOnDelete();
            // Resolved column map actually used for this batch (copied from
            // the profile, or the ad hoc map submitted at upload time) so
            // /revalidate can re-run without the client resending it.
            $table->json('column_map');
            $table->string('original_filename', 255);
            $table->string('disk_path', 500);
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('entity_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
