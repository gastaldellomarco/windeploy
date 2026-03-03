<?php
// database/migrations/2026_03_03_000005_add_updated_at_to_software_library_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('software_library', function (Blueprint $table) {
            // add updated_at if it doesn't exist (existing migration created only created_at)
            if (!Schema::hasColumn('software_library', 'updated_at')) {
                // nullable to avoid issues during migration on existing rows
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->useCurrent();
            }
        });
    }

    public function down(): void
    {
        Schema::table('software_library', function (Blueprint $table) {
            if (Schema::hasColumn('software_library', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
