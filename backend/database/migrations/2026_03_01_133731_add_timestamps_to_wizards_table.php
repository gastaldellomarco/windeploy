<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Add missing timestamps to match Eloquent defaults
            if (!Schema::hasColumn('wizards', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('stato');
            }
            if (!Schema::hasColumn('wizards', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Rollback cautiously
            if (Schema::hasColumn('wizards', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
            if (Schema::hasColumn('wizards', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};
