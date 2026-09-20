<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D105: the authorization letter's own form collects an address.
        Schema::table('artist_contacts', function (Blueprint $table) {
            $table->text('address')->nullable();
        });

        // D106, D107.
        Schema::table('artworks', function (Blueprint $table) {
            $table->string('material_classification', 20)->default('movable');
            $table->text('conservation_risk_note')->nullable();
        });

        // D110: a source can be an Archive Item already in the platform.
        Schema::table('sources', function (Blueprint $table) {
            $table->foreignId('linked_archive_item_id')->nullable()->constrained('archive_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_archive_item_id');
        });
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropColumn(['material_classification', 'conservation_risk_note']);
        });
        Schema::table('artist_contacts', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
