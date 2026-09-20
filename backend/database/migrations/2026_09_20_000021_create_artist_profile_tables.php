<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->string('nationality_ar', 120)->nullable();
            $table->string('nationality_en', 120)->nullable();
            $table->string('classification_ar', 120)->nullable();
            $table->string('classification_en', 120)->nullable();
            $table->string('portrait_path', 500)->nullable();
            $table->string('portrait_rights_status', 20)->default('unknown');
        });

        Schema::create('artist_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('role_note', 255)->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Single contact columns become the first row of the new list. The
        // encrypted values are copied as-is (same key, same cast).
        foreach (DB::table('artists')->whereNotNull('key_contact_name')->orWhereNotNull('contact_email')->orWhereNotNull('contact_phone')->get() as $a) {
            DB::table('artist_contacts')->insert([
                'artist_id' => $a->id, 'name' => $a->key_contact_name, 'email' => $a->contact_email, 'phone' => $a->contact_phone,
                'sort' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['key_contact_name', 'contact_email', 'contact_phone']);
        });

        Schema::create('artist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->string('place_ar', 255)->nullable();
            $table->string('place_en', 255)->nullable();
            $table->smallInteger('year_from')->nullable();
            $table->smallInteger('year_to')->nullable();
            $table->text('note_ar')->nullable();
            $table->text('note_en')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['artist_id', 'type']);
        });

        Schema::create('artist_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('url', 500);
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_social_links');
        Schema::dropIfExists('artist_entries');
        Schema::table('artists', function (Blueprint $table) {
            $table->string('key_contact_name', 255)->nullable();
            $table->text('contact_email')->nullable();
            $table->text('contact_phone')->nullable();
        });
        Schema::dropIfExists('artist_contacts');
        Schema::table('artists', function (Blueprint $table) {
            $table->dropColumn(['nationality_ar', 'nationality_en', 'classification_ar', 'classification_en', 'portrait_path', 'portrait_rights_status']);
        });
    }
};
