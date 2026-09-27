<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Reviewer normally cannot edit/delete already-published content directly;
        // this is the pending-approval gate that unlocks it, decided by an Admin.
        Schema::create('content_permission_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->string('request_type', 10);
            $table->foreignId('requested_by_user_id')->constrained('users');
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['citable_type', 'citable_id']);
            $table->index(['status', 'requested_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_permission_requests');
    }
};
