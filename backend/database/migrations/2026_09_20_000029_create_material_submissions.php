<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submitter_name', 255);
            $table->string('submitter_contact', 255);
            $table->string('submitter_role', 20);
            $table->string('city', 120)->nullable();
            $table->text('description');
            // 30, not the spec's 20: `authorization_pending` alone is 21 characters.
            $table->string('status', 30)->default('submitted');
            $table->foreignId('linked_artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->foreignId('linked_artwork_id')->nullable()->constrained('artworks')->nullOnDelete();
            $table->string('authorization_letter_status', 20)->default('not_started');
            // Either a staged file or, once cataloged, a real one; a morph keeps that honest (D157 note).
            $table->string('authorization_letter_type')->nullable();
            $table->unsignedBigInteger('authorization_letter_id')->nullable();
            $table->text('staff_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();

            $table->index('status');
        });

        DB::statement("ALTER TABLE material_submissions ADD CONSTRAINT material_submissions_role_check CHECK (submitter_role IN ('artist','artist_family','private_collection','association','researcher','other'))");
        DB::statement("ALTER TABLE material_submissions ADD CONSTRAINT material_submissions_status_check CHECK (status IN ('submitted','initial_review','cataloging','authorization_pending','published','rejected','withdrawn'))");
        DB::statement("ALTER TABLE material_submissions ADD CONSTRAINT material_submissions_auth_check CHECK (authorization_letter_status IN ('not_started','pending','signed','not_applicable'))");

        Schema::create('material_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_submission_id')->constrained('material_submissions')->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('original_filename', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('archive_items', function (Blueprint $table) {
            $table->foreignId('source_material_submission_id')->nullable()->constrained('material_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('archive_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_material_submission_id');
        });
        Schema::dropIfExists('material_submission_files');
        Schema::dropIfExists('material_submissions');
    }
};
