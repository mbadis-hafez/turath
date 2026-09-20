<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_ref', 40)->nullable()->unique();
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('attribution_certainty', 20)->default('unattributed');
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->boolean('is_untitled')->default(false);
            $table->string('category', 20);
            $table->string('medium_ar', 255)->nullable();
            $table->string('medium_en', 255)->nullable();
            $table->string('edition_number', 20)->nullable();
            $table->smallInteger('edition_size')->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('width_cm', 6, 2)->nullable();
            $table->decimal('depth_cm', 6, 2)->nullable();
            $table->decimal('frame_height_cm', 6, 2)->nullable();
            $table->decimal('frame_width_cm', 6, 2)->nullable();
            $table->decimal('frame_depth_cm', 6, 2)->nullable();
            $table->string('dimensions_raw', 255)->nullable();
            $table->string('frame_dimensions_raw', 255)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('signed', 20)->default('unknown');
            $table->partialDate('creation');
            $table->foreignId('holder_id')->nullable()->constrained('holders')->nullOnDelete();
            $table->string('holder_inventory_no', 60)->nullable();
            $table->text('notes_ar')->nullable();
            $table->text('notes_en')->nullable();
            $table->string('publication_status', 20)->default('draft');
            $table->text('search_text')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('artist_id');
            $table->index('holder_id');
            $table->index('category');
            $table->index('publication_status');
        });

        DB::statement('ALTER TABLE artworks ADD CONSTRAINT artworks_title_required_check CHECK (is_untitled = 1 OR title_ar IS NOT NULL OR title_en IS NOT NULL)');

        // No DB-level CHECK ties attribution_certainty to artist_id: MySQL 8
        // (error 3823) refuses a CHECK constraint on a column that
        // participates in a foreign key's ON DELETE SET NULL action. This
        // invariant is enforced in ArtworkPayloadRequest instead.
        DB::statement('ALTER TABLE artworks ADD CONSTRAINT artworks_creation_year_order_check CHECK (creation_year_from IS NULL OR creation_year_to IS NULL OR creation_year_from <= creation_year_to)');
        DB::statement('ALTER TABLE artworks ADD CONSTRAINT artworks_dimensions_nonnegative_check CHECK (
            (height_cm IS NULL OR height_cm >= 0) AND
            (width_cm IS NULL OR width_cm >= 0) AND
            (depth_cm IS NULL OR depth_cm >= 0) AND
            (frame_height_cm IS NULL OR frame_height_cm >= 0) AND
            (frame_width_cm IS NULL OR frame_width_cm >= 0) AND
            (frame_depth_cm IS NULL OR frame_depth_cm >= 0) AND
            (weight_kg IS NULL OR weight_kg >= 0)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('artworks');
    }
};
