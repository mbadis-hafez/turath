<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per authorization-letter file: which Artist a reviewer confirmed
     * the document belongs to, and which editorial draft carries the proposed
     * ArtistContact change. The contact values themselves live only in that
     * draft's payload (and, once approved, in the encrypted artist_contacts
     * row) — this table holds provenance, never a second copy of the values.
     */
    public function up(): void
    {
        Schema::create('file_ocr_contact_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->foreignId('artist_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('artist_confirmed_at')->nullable();
            $table->foreignUuid('edit_proposal_id')->nullable()->constrained('edit_proposals')->nullOnDelete();
            $table->foreignId('target_contact_id')->nullable()->constrained('artist_contacts')->nullOnDelete();
            $table->json('provenance')->nullable();
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_ocr_contact_proposals');
    }
};
