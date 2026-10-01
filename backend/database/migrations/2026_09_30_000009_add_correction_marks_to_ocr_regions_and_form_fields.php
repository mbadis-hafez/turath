<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A possible correction mark (a scribbled-out word, a line struck through
     * text) on a region, and on any form field whose value sits in it. A mark
     * never picks a value; it forces a reviewer to.
     */
    public function up(): void
    {
        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->boolean('has_correction_mark')->default(false)->after('requires_human_review');
            // [{kind: scribble|line_strike, bbox: {x, y, width, height}}] in page pixels
            $table->json('correction_marks')->nullable()->after('has_correction_mark');
        });

        Schema::table('file_ocr_form_fields', function (Blueprint $table) {
            $table->boolean('has_correction_mark')->default(false)->after('requires_manual_transcription');
        });
    }

    public function down(): void
    {
        Schema::table('file_ocr_form_fields', function (Blueprint $table) {
            $table->dropColumn('has_correction_mark');
        });

        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->dropColumn(['has_correction_mark', 'correction_marks']);
        });
    }
};
