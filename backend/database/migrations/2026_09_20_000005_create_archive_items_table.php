<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_items', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_ref', 80)->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('archive_items')->nullOnDelete();
            $table->string('item_type', 20);
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('creator_name', 255)->nullable();
            $table->string('publication_name_ar', 255)->nullable();
            $table->string('publication_name_en', 255)->nullable();
            $table->string('issue_no', 30)->nullable();
            $table->string('page', 30)->nullable();
            $table->string('language', 3)->nullable();
            $table->string('original_format', 20)->nullable();
            $table->string('source_filename', 255)->nullable();
            $table->string('quality_flag', 20)->default('high');
            $table->partialDate('content');
            $table->date('digitized_at')->nullable();
            $table->string('access_level', 20)->default('institution_only');
            $table->date('embargo_until')->nullable();
            $table->string('post_embargo_access_level', 20)->default('public');
            $table->string('rights_status', 20)->default('unknown');
            $table->string('rights_holder_ar', 255)->nullable();
            $table->string('rights_holder_en', 255)->nullable();
            $table->string('license', 120)->nullable();
            $table->string('consent_status', 20)->default('unknown');
            $table->string('publication_status', 20)->default('draft');
            $table->text('search_text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('item_type');
            $table->index('access_level');
            $table->index('publication_status');
            $table->index('parent_id');
        });

        DB::statement('ALTER TABLE archive_items ADD CONSTRAINT archive_items_embargo_date_check CHECK (access_level <> \'embargoed\' OR embargo_until IS NOT NULL)');
        DB::statement('ALTER TABLE archive_items ADD CONSTRAINT archive_items_not_blank_check CHECK (title_ar IS NOT NULL OR title_en IS NOT NULL OR description_ar IS NOT NULL OR description_en IS NOT NULL)');
        DB::statement('ALTER TABLE archive_items ADD CONSTRAINT archive_items_content_year_order_check CHECK (content_year_from IS NULL OR content_year_to IS NULL OR content_year_from <= content_year_to)');
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_items');
    }
};
