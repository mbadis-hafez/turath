<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 20);
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('venue_name', 255)->nullable();
            $table->string('city', 255)->nullable();
            $table->foreignId('holder_id')->nullable()->constrained('holders')->nullOnDelete();
            $table->partialDate('start');
            $table->partialDate('end');
            $table->string('date_note', 500)->nullable();
            $table->string('publication_status', 20)->default('draft');
            $table->string('access_level', 20)->default('public');
            $table->text('search_text')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_type');
            $table->index('publication_status');
            $table->index('start_year_from');
        });

        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->string('role', 20);
            $table->string('note', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'participant_type', 'participant_id', 'role'], 'event_participants_unique');
            $table->index(['participant_type', 'participant_id']);
        });

        // D123: one taxonomy for artists, artworks and events.
        Schema::create('theme_taggables', function (Blueprint $table) {
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->primary(['theme_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        DB::statement("INSERT INTO theme_taggables (theme_id, taggable_type, taggable_id) SELECT theme_id, 'App\\\\Models\\\\Artist', artist_id FROM artist_themes");
        Schema::dropIfExists('artist_themes');
    }

    public function down(): void
    {
        Schema::create('artist_themes', function (Blueprint $table) {
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->primary(['artist_id', 'theme_id']);
        });
        DB::statement("INSERT INTO artist_themes (artist_id, theme_id) SELECT taggable_id, theme_id FROM theme_taggables WHERE taggable_type = 'App\\\\Models\\\\Artist'");

        Schema::dropIfExists('theme_taggables');
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('events');
    }
};
