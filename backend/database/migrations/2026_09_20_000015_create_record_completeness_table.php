<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_completeness', function (Blueprint $table) {
            $table->string('citable_type');
            $table->unsignedBigInteger('citable_id');
            $table->unsignedTinyInteger('completeness_pct')->default(0);
            $table->json('blocking_gap_field_keys');
            $table->json('minor_gap_field_keys');
            $table->unsignedInteger('open_conflict_count')->default(0);
            $table->string('severity', 20)->default('blocking');
            $table->timestamp('computed_at')->useCurrent();

            $table->primary(['citable_type', 'citable_id']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_completeness');
    }
};
