<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edit_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->foreignId('proposed_by_user_id')->constrained('users');
            $table->string('status', 20)->default('pending');
            $table->json('field_diffs');
            $table->text('rationale');
            // D68 deviation: the proposed citations are stored as payloads here, not as
            // pending rows in field_citations, so nothing unreviewed can reach F10's
            // completeness or ConflictDetector before approval.
            $table->json('proposed_citations')->nullable();
            $table->string('review_type', 30);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignUuid('resulting_revision_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['citable_type', 'citable_id']);
            $table->index(['status', 'review_type']);
        });

        Schema::create('revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->unsignedInteger('revision_number');
            $table->string('source', 20);
            $table->foreignUuid('edit_proposal_id')->nullable()->constrained('edit_proposals')->nullOnDelete();
            $table->json('field_diffs');
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignUuid('reverted_by_revision_id')->nullable();

            $table->unique(['citable_type', 'citable_id', 'revision_number'], 'revisions_record_number_unique');
            $table->index(['citable_type', 'citable_id']);
        });

        // Declared after both tables exist, since they reference each other.
        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->foreign('resulting_revision_id')->references('id')->on('revisions')->nullOnDelete();
        });
        Schema::table('revisions', function (Blueprint $table) {
            $table->foreign('reverted_by_revision_id')->references('id')->on('revisions')->nullOnDelete();
        });

        // D76: proposals populate F10's queue, so a row can point back at its proposal.
        Schema::table('review_queue_items', function (Blueprint $table) {
            $table->foreignUuid('edit_proposal_id')->nullable()->after('citable_id')->constrained('edit_proposals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_queue_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edit_proposal_id');
        });
        Schema::table('revisions', function (Blueprint $table) {
            $table->dropForeign(['reverted_by_revision_id']);
        });
        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->dropForeign(['resulting_revision_id']);
        });
        Schema::dropIfExists('revisions');
        Schema::dropIfExists('edit_proposals');
    }
};
