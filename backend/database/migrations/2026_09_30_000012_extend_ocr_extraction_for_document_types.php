<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document-type-aware extraction (see App\Support\Ocr\Extraction):
     *
     * - file_extracted_fields gains which schema a field belongs to, an
     *   ordinal so list fields (an exhibition history) get one row per item,
     *   the form field it was read from, the rule that found it, and
     *   verified_value — so a reviewer's edit no longer overwrites what the
     *   machine read. extracted_value stays the machine layer.
     * - file_extracted_dates gains the schema field a date fills, a
     *   normalized form in its own calendar, the text around it, and the same
     *   review state as fields.
     * - files record when a reviewer, not the classifier, chose the type.
     *
     * Additive only; no existing value is changed.
     */
    public function up(): void
    {
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->string('document_type', 40)->nullable()->after('field_key');
            $table->unsignedSmallInteger('ordinal')->default(0)->after('document_type');
            $table->text('verified_value')->nullable()->after('extracted_value');
            $table->foreignId('form_field_id')->nullable()->after('region_id')->constrained('file_ocr_form_fields')->nullOnDelete();
            $table->string('rule', 40)->nullable()->after('extraction_method');
            // Added before the old index is dropped: MySQL needs an index leading with file_id for its foreign key.
            $table->unique(['file_id', 'field_key', 'ordinal'], 'file_extracted_fields_key_ordinal_unique');
        });
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->dropUnique(['file_id', 'field_key']);
        });

        Schema::table('file_extracted_dates', function (Blueprint $table) {
            $table->string('normalized', 20)->nullable()->after('value');
            $table->string('field_key', 60)->nullable()->after('date_type');
            $table->string('context', 255)->nullable()->after('source_method');
            $table->unsignedTinyInteger('confidence')->nullable()->after('context');
            $table->string('status', 20)->default('pending')->after('confidence');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('document_type_set_by_user_id')->nullable()->after('document_type')->constrained('users')->nullOnDelete();
            $table->timestamp('document_type_set_at')->nullable()->after('document_type_set_by_user_id');
        });
    }

    /** Fails, rather than deleting rows, if a list field has more than one item stored. */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_type_set_by_user_id');
            $table->dropColumn('document_type_set_at');
        });

        Schema::table('file_extracted_dates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['normalized', 'field_key', 'context', 'confidence', 'status', 'reviewed_at']);
        });

        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->unique(['file_id', 'field_key']);
        });
        Schema::table('file_extracted_fields', function (Blueprint $table) {
            $table->dropUnique('file_extracted_fields_key_ordinal_unique');
            $table->dropConstrainedForeignId('form_field_id');
            $table->dropColumn(['document_type', 'ordinal', 'verified_value', 'rule']);
        });
    }
};
