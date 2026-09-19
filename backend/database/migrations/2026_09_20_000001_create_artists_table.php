<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_code', 20)->nullable()->unique();
            $table->string('slug', 160)->unique();
            $table->string('name_ar', 255)->nullable();
            $table->string('name_en', 255)->nullable();
            $table->text('bio_ar')->nullable();
            $table->text('bio_en')->nullable();
            $table->partialDate('birth');
            $table->string('birth_place_ar', 255)->nullable();
            $table->string('birth_place_en', 255)->nullable();
            $table->partialDate('death');
            $table->string('death_place_ar', 255)->nullable();
            $table->string('death_place_en', 255)->nullable();
            $table->string('living_status', 20)->default('unknown');
            $table->string('verified_status', 20)->default('unverified');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('publication_status', 20)->default('draft');
            $table->text('search_text')->nullable();
            $table->text('search_compact')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('publication_status');
            $table->index('verified_status');
        });

        DB::statement('ALTER TABLE artists ADD CONSTRAINT artists_name_required_check CHECK (name_ar IS NOT NULL OR name_en IS NOT NULL)');
        DB::statement('ALTER TABLE artists ADD CONSTRAINT artists_birth_year_order_check CHECK (birth_year_from IS NULL OR birth_year_to IS NULL OR birth_year_from <= birth_year_to)');
        DB::statement('ALTER TABLE artists ADD CONSTRAINT artists_death_year_order_check CHECK (death_year_from IS NULL OR death_year_to IS NULL OR death_year_from <= death_year_to)');
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
