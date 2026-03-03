<?php
// database/migrations/2024_01_01_000003_create_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizards', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->foreignId('template_id')
                  ->nullable()
                  ->nullOnDelete()                               // se il template viene eliminato, NULL
                  ->constrained('templates');
            $table->string('codice_univoco', 10)->unique();      // es. "WD-7A3F"
            $table->enum('stato', [
                'bozza',
                'pronto',
                'in_esecuzione',
                'completato',
                'errore'
            ])->default('bozza');

            // ⚠️ SICUREZZA: configurazione contiene utente_admin.password_encrypted
            // NON salvare mai la password in chiaro — vedi sezione dedicata
            $table->json('configurazione');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();         // +24h da created_at, settato nel model
            $table->timestamp('used_at')->nullable();            // monouso: impostato all'avvio agent

            // Indici
            $table->index('stato');                              // lista wizard per stato (frequente)
            $table->index('user_id');
            $table->index('codice_univoco');                     // lookup agent (criticamente frequente)
            $table->index('expires_at');                         // pulizia wizard scaduti (scheduled job)
            $table->index(['stato', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizards');
    }
};
