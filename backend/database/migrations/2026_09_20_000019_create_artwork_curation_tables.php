<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artworks', function (Blueprint $table) {
            $table->string('condition_report_link', 500)->nullable();
            $table->string('condition_report_status', 20)->nullable();
            $table->string('image_quality', 20)->nullable();
            $table->string('editing_status', 20)->nullable();
            $table->foreignId('final_selected_hr_image_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('inventory_by_owner', 120)->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('artworks')->nullOnDelete();
        });

        Schema::create('artwork_pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('artwork_id')->constrained('artworks')->cascadeOnDelete();
            $table->string('stage_key', 30);
            $table->string('status', 20)->default('not_started');
            $table->text('note')->nullable();
            $table->foreignId('linked_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['artwork_id', 'stage_key']);
        });

        Schema::create('pipeline_note_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('artwork_id')->constrained('artworks')->cascadeOnDelete();
            $table->string('stage_key', 30);
            $table->text('note');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('candidate_artworks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type', 30);
            $table->foreignId('source_archive_item_id')->nullable()->constrained('archive_items')->nullOnDelete();
            $table->foreignUuid('source_import_batch_row_id')->nullable()->constrained('import_batch_rows')->nullOnDelete();
            $table->string('suggested_title_ar', 255)->nullable();
            $table->string('suggested_title_en', 255)->nullable();
            $table->foreignId('suggested_artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('promoted_artwork_id')->nullable()->constrained('artworks')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('artwork_merges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('survivor_artwork_id')->constrained('artworks');
            $table->foreignId('merged_artwork_id')->constrained('artworks');
            $table->json('field_resolution');
            $table->foreignId('merged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artwork_merges');
        Schema::dropIfExists('candidate_artworks');
        Schema::dropIfExists('pipeline_note_suggestions');
        Schema::dropIfExists('artwork_pipeline_stages');
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropConstrainedForeignId('final_selected_hr_image_file_id');
            $table->dropColumn(['condition_report_link', 'condition_report_status', 'image_quality', 'editing_status', 'inventory_by_owner']);
        });
    }
};
