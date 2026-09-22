<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')->nullable()->after('ref_supervisor_note')
                ->constrained('users')->nullOnDelete();
            $table->index('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('artists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to_user_id');
        });
    }
};
