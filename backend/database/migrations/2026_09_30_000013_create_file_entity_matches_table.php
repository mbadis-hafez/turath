<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidate records for a name or title extracted from a document — one
     * row per extracted field that names an artist, artwork, exhibition,
     * institution, source or place. Candidates are ids plus the evidence for
     * each (labels are read from the records when shown, so a renamed record
     * or a private holder is displayed correctly). A match is never decided
     * automatically: it stays pending until a reviewer confirms a record or
     * says none of them is right. confirmed_entity_id is a string because
     * sources are keyed by uuid; a place is confirmed by its spelling
     * (confirmed_key), since there is no place table.
     */
    public function up(): void
    {
        Schema::create('file_entity_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extracted_field_id')->unique()->constrained('file_extracted_fields')->cascadeOnDelete();
            $table->string('entity_type', 20);
            $table->text('source_text');
            $table->json('candidates');
            $table->string('status', 20)->default('pending');
            $table->string('confirmed_entity_id', 36)->nullable();
            $table->string('confirmed_key', 255)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('matcher_version', 20);
            $table->timestamps();

            $table->index(['file_id', 'entity_type']);
            $table->index(['entity_type', 'confirmed_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_entity_matches');
    }
};
