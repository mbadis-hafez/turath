<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviewer state the review screen needs:
     *
     * - file_extracted_fields.review_note: why a reviewer marked a value
     *   uncertain (status "uncertain": looked at, can't be confirmed from the
     *   source — never applied, never bulk-accepted, still open to a later
     *   accept, edit or reject).
     * - file_ocr_regions.review_dismissed_*: a line the pipeline set aside for
     *   a person (a possible strikethrough, handwriting no form field covers)
     *   that the reviewer looked at and found nothing to extract from. Regions
     *   are recreated when OCR re-runs, so a dismissal lasts until then.
     */
    public function up(): void
    {
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->foreignId('review_dismissed_by_user_id')->nullable()->after('review_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('review_dismissed_at')->nullable()->after('review_dismissed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('file_ocr_regions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_dismissed_by_user_id');
            $table->dropColumn('review_dismissed_at');
        });
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->dropColumn('review_note');
        });
    }
};
