<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            if (!Schema::hasColumn('wizards', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            if (Schema::hasColumn('wizards', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
