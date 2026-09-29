<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('ocr_status', 20)->nullable()->after('duration_seconds');
            $table->unsignedTinyInteger('ocr_progress_pct')->nullable()->after('ocr_status');
            $table->timestamp('ocr_completed_at')->nullable()->after('ocr_progress_pct');
            $table->json('ocr_language_confidence')->nullable()->after('ocr_completed_at');
            $table->text('ocr_failure_reason')->nullable()->after('ocr_language_confidence');

            $table->index('ocr_status');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['ocr_status']);
            $table->dropColumn(['ocr_status', 'ocr_progress_pct', 'ocr_completed_at', 'ocr_language_confidence', 'ocr_failure_reason']);
        });
    }
};
