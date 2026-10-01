<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per contact value (email, phone, address) a reviewer proposed
     * from a document, with its own state: pending → approved | rejected |
     * changes_requested | superseded. The editorial draft it travels in is
     * still what a second reviewer approves and what writes ArtistContact;
     * these rows record what was proposed, from where, and what became of it.
     *
     * The proposed value is encrypted like artist_contacts. The value it would
     * replace is never kept here — while the proposal is open it is read live
     * from the contact, and once replaced it is gone, as contact history is
     * everywhere else (the activity log records counts, never values).
     * file_ocr_contact_proposals.provenance predates these rows and is no
     * longer written; it is left in place rather than dropped.
     *
     * edit_proposals.base_fingerprints holds a keyed hash of each child
     * collection a draft replaces wholesale (currently the artist's contacts),
     * taken when the draft was written, so approving it after that collection
     * changed is a conflict the reviewer must confirm rather than a silent
     * revert. Keyed so a stored hash can't be brute-forced into a phone number.
     */
    public function up(): void
    {
        Schema::create('artist_contact_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->foreignUuid('edit_proposal_id')->nullable()->constrained('edit_proposals')->nullOnDelete();
            // new_contact | update_contact — kept even if the target contact is later deleted
            $table->string('action', 20);
            $table->foreignId('target_contact_id')->nullable()->constrained('artist_contacts')->nullOnDelete();
            // email | phone | address
            $table->string('field', 20);
            $table->text('proposed_value');
            $table->boolean('replaces_existing')->default(false);
            // pending | changes_requested | approved | rejected | superseded
            $table->string('status', 20);
            // newer_proposal | draft_rewritten | other_proposal_approved
            $table->string('superseded_reason', 30)->nullable();
            // Provenance, snapshotted: re-running OCR replaces regions and form fields.
            $table->unsignedInteger('source_page')->nullable();
            $table->foreignId('source_region_id')->nullable()->constrained('file_ocr_regions')->nullOnDelete();
            $table->json('source_bbox')->nullable();
            $table->foreignId('source_form_field_id')->nullable()->constrained('file_ocr_form_fields')->nullOnDelete();
            $table->string('source_label', 255)->nullable();
            $table->string('extraction_method', 30);
            $table->unsignedTinyInteger('confidence')->nullable();
            // {provider, model, model_version, confidence, decision} — never the suggested text
            $table->json('machine_suggestion')->nullable();
            $table->boolean('has_correction_mark')->default(false);
            $table->boolean('edited_by_proposer')->default(false);
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['file_id', 'status']);
            $table->index(['artist_id', 'field', 'status']);
        });

        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->json('base_fingerprints')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->dropColumn('base_fingerprints');
        });
        Schema::dropIfExists('artist_contact_proposals');
    }
};
