<?php
// database/migrations/2024_01_01_000006_create_reports_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_log_id')
                  ->unique()                                     // 1:1 con execution_log
                  ->constrained('execution_logs')
                  ->cascadeOnDelete();                           // se il log viene rimosso, rimuovi il report
            $table->longText('html_content');                    // report HTML auto-contenuto
            $table->timestamp('created_at')->useCurrent();

            // Indici
            $table->index('created_at');                         // lista report per data (frequente)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
