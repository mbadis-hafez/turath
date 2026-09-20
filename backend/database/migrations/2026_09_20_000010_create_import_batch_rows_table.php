<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->string('match_status', 20)->default('new');
            // Matched/resulting entities are Artist/Holder/Artwork/ArchiveItem
            // rows, which all use bigint auto-increment ids (F1-F3), not
            // uuids — these two columns intentionally diverge from the
            // spec's literal "uuid" type to actually reference those tables.
            $table->unsignedBigInteger('matched_entity_id')->nullable();
            $table->string('match_confidence', 10)->nullable();
            $table->string('resolution', 20)->default('pending');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('commit_result', 20)->nullable();
            $table->unsignedBigInteger('resulting_entity_id')->nullable();
            $table->timestamps();

            $table->index('import_batch_id');
            $table->index('match_status');
            $table->index('resolution');
            $table->unique(['import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_rows');
    }
};
