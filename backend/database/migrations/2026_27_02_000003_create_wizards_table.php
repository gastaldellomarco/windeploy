<?php
// database/migrations/2024_01_01_000004_create_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizards', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);

            // cascadeOnDelete: se l'utente viene eliminato, i suoi wizard spariscono.
            // ATTENZIONE: questo causa cascade anche su execution_logs se non protetto.
            // La protezione è su execution_logs.wizard_id con restrictOnDelete.
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // nullOnDelete: se il template viene eliminato (soft o hard),
            // il wizard mantiene la propria configurazione JSON autonoma.
            // Il template è solo "da dove è partito", non è necessario per l'esecuzione.
            $table->foreignId('template_id')
                  ->nullable()
                  ->constrained('templates')
                  ->nullOnDelete();

            // Formato WD-XXXX dove XXXX sono 4 caratteri alfanumerici uppercase.
            // UNIQUE a livello DB: l'agent usa questo come chiave di lookup primaria.
            $table->string('codice_univoco', 10)->unique();

            $table->enum('stato', [
                'bozza',        // in costruzione, non ancora distribuibile
                'pronto',       // generato con codice, pronto per l'agent
                'in_esecuzione',// agent sta lavorando
                'completato',   // esecuzione terminata con successo
                'errore',       // esecuzione fallita
            ])->default('bozza');

            // ⚠️ SICUREZZA CRITICA: questo campo contiene utente_admin.password_encrypted
            // e potenzialmente extras.wifi.password_encrypted.
            // REGOLA: mai esporre nelle API generali — vedi WizardResource che li rimuove.
            // La decifrazione avviene SOLO nell'endpoint /api/agent/start (JWT protetto).
            $table->json('configurazione');

            $table->timestamps(); // created_at + updated_at standard

            // expires_at: +24h da created_at, impostato nel Model boot() o nel Controller.
            // Lo scheduled job (app/Console/Commands/PurgeExpiredWizards) usa questo indice.
            $table->timestamp('expires_at')->nullable();

            // used_at: impostato quando l'agent esegue /api/agent/start.
            // Rende il wizard "monouso" — un secondo tentativo riceve 409 Conflict.
            $table->timestamp('used_at')->nullable();

            $table->softDeletes();

            // Indici critici per performance
            $table->unique('codice_univoco'); // già dichiarato sopra, ridondante ma esplicito
            $table->index('stato');
            $table->index('user_id');
            $table->index('expires_at');      // per il job di pulizia schedulato
            $table->index(['stato', 'user_id']); // dashboard: wizard per tecnico+stato
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
