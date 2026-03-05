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

            // cascadeOnDelete: se il log viene eliminato, il report sparisce.
            // Relazione 1:1 — un log ha al massimo un report.
            // unique() garantisce l'unicità a livello DB.
            $table->foreignId('execution_log_id')
                  ->unique()
                  ->constrained('execution_logs')
                  ->cascadeOnDelete();

            // LONGTEXT: un report HTML completo con CSS inline può arrivare a 10-50KB.
            // LONGTEXT supporta fino a 4GB — più che sufficiente.
            $table->longText('html_content');

            // Solo created_at: i report sono immutabili una volta generati.
            // Se serve ri-generare, si elimina e ricrea (cascade dal log).
            $table->timestamp('created_at')->useCurrent();

            // Indice su execution_log_id già creato dall'unique() sopra.
            // Aggiungiamo indice su created_at per query "report recenti".
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
