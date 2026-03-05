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

            // restrictOnDelete: NON si può eliminare un wizard che ha log associati.
            // Questo protegge l'audit trail. Per eliminare un wizard con log,
            // bisogna prima archiviare/eliminare i log — scelta deliberata.
            $table->foreignId('wizard_id')
                  ->constrained('wizards')
                  ->restrictOnDelete();

            $table->string('pc_nome_originale', 255)->nullable();
            $table->string('pc_nome_nuovo', 255)->nullable();

            // nullOnDelete: se il tecnico viene rimosso, il log rimane (audit trail)
            // ma tecnico_user_id diventa NULL. Il report mostra "tecnico rimosso".
            $table->foreignId('tecnico_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // JSON hardware: { "cpu": "Intel i7-12700", "ram_gb": 16,
            //                  "disco_gb": 512, "windows_version": "11 Pro 23H2" }
            $table->json('hardware_info')->nullable();

            $table->enum('stato', [
                'avviato',      // agent ha chiamato /api/agent/start
                'in_corso',     // step intermedi in arrivo
                'completato',   // tutti gli step ok
                'errore',       // almeno uno step critico fallito
                'abortito',     // utente o sistema ha interrotto
            ])->default('avviato');

            // JSON array di step:
            // [{"step": "rename_pc", "timestamp": "2026-03-04T20:00:00Z",
            //   "esito": "ok", "dettaglio": "PC rinominato da DESKTOP-ABC a PC-CONT-01"}]
            $table->json('log_dettagliato')->nullable();

            // execution_logs NON usa timestamps() standard di Laravel:
            // started_at e completed_at sono semanticamente diversi da created_at/updated_at.
            // Il Model ha $timestamps = false per evitare errori Eloquent.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Indici per dashboard e report
            $table->index('wizard_id');
            $table->index('stato');
            $table->index('tecnico_user_id');
            $table->index('started_at');                  // report per data
            $table->index(['stato', 'started_at']);       // dashboard: completati ultima settimana
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
