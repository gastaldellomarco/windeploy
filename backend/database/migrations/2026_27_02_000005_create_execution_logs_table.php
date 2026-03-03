<?php
// database/migrations/2024_01_01_000005_create_execution_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wizard_id')
                  ->constrained('wizards')
                  ->restrictOnDelete();                          // non cancellare wizard con log attivi
            $table->string('pc_nome_originale', 100)->nullable();
            $table->string('pc_nome_nuovo', 100)->nullable();
            $table->foreignId('tecnico_user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            // JSON hardware: { "cpu": "...", "ram_gb": 16, "disco_gb": 512, "windows_version": "..." }
            $table->json('hardware_info')->nullable();

            $table->enum('stato', [
                'avviato',
                'in_corso',
                'completato',
                'errore',
                'abortito'
            ])->default('avviato');

            // JSON array di step: [{"step": "rename_pc", "timestamp": "...", "esito": "ok", "dettaglio": "..."}]
            $table->json('log_dettagliato')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            // Indici
            $table->index('stato');
            $table->index('wizard_id');
            $table->index('tecnico_user_id');
            $table->index('started_at');                         // report per data
            $table->index(['stato', 'started_at']);              // dashboard: completati ultima settimana
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
