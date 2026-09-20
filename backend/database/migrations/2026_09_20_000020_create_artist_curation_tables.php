<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('identified_through_note', 255)->nullable();
            $table->date('identified_through_date')->nullable();
            $table->json('name_as_in_sources')->nullable();
            $table->string('key_contact_name', 255)->nullable();
            $table->string('owner_type', 20)->nullable();
            $table->text('contact_email')->nullable();
            $table->text('contact_phone')->nullable();
            $table->string('authorization_letter_status', 20)->default('not_started');
            $table->foreignId('authorization_letter_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('owner_pre_agreement_status', 20)->default('not_started');
            $table->string('bio_source_type', 40)->default('unspecified');
            $table->string('ref_supervisor_note', 255)->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('artists')->nullOnDelete();
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('label_ar', 255)->nullable();
            $table->string('label_en', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('artist_themes', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->primary(['artist_id', 'theme_id']);
        });

        Schema::create('artist_merges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('survivor_artist_id')->constrained('artists');
            $table->foreignId('merged_artist_id')->constrained('artists');
            $table->json('field_resolution');
            $table->foreignId('merged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_merges');
        Schema::dropIfExists('artist_themes');
        Schema::dropIfExists('themes');
        Schema::table('artists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropConstrainedForeignId('authorization_letter_file_id');
            $table->dropColumn(['identified_through_note', 'identified_through_date', 'name_as_in_sources', 'key_contact_name', 'owner_type', 'contact_email', 'contact_phone', 'authorization_letter_status', 'owner_pre_agreement_status', 'bio_source_type', 'ref_supervisor_note']);
        });
    }
};
