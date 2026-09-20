<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_queue_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->string('review_type', 30);
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->string('note', 255)->nullable();
            $table->string('status', 20)->default('pending');

            $table->index(['citable_type', 'citable_id']);
            $table->index(['review_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_queue_items');
    }
};
