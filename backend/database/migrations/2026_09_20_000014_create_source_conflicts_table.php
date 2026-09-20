<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_conflicts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->string('field_key', 60);
            $table->string('status', 20)->default('open');
            $table->json('citation_ids');
            $table->foreignUuid('resolved_source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['citable_type', 'citable_id', 'field_key'], 'source_conflicts_citable_field_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_conflicts');
    }
};
