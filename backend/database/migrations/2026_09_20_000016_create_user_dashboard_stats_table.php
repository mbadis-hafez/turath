<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_dashboard_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedTinyInteger('avg_completeness_pct')->default(0);
            $table->unsignedInteger('blocking_record_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('missing_field_count')->default(0);
            $table->json('by_entity_type');
            $table->timestamp('computed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_stats');
    }
};
