<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_citations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            // Every real citable entity (Artist, Artwork, ArchiveItem) uses a
            // bigint auto-increment id, so this is unsignedBigInteger rather
            // than uuid, same reasoning as F4's D34.
            $table->unsignedBigInteger('citable_id');
            $table->string('field_key', 60);
            $table->foreignUuid('source_id')->constrained('sources');
            $table->json('claimed_value');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['citable_type', 'citable_id', 'field_key'], 'field_citations_citable_field_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_citations');
    }
};
