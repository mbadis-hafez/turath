<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editorial drafts: the staged, section-structured payload an editor is
        // working on, validated like the endpoint bodies it mirrors. The status
        // column stays plain VARCHAR(20) — new statuses need no schema change.
        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('field_diffs');
        });
    }

    public function down(): void
    {
        Schema::table('edit_proposals', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
