<?php
// database/migrations/2024_01_01_000002_create_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->text('descrizione')->nullable();

            // cascadeOnDelete: se l'utente viene eliminato (soft delete non basta,
            // ma con softDeletes sulla tabella users non arriveremo mai qui in prod).
            // Scelta: cascadeOnDelete per coerenza — se un admin cancella fisicamente
            // un utente test, i suoi template personali spariscono con lui.
            // I template globali (scope='globale') sono sempre di un admin esistente.
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('scope', ['globale', 'personale'])->default('personale');

            // JSON con stessa struttura di wizards.configurazione (parziale o completa)
            $table->json('configurazione');

            $table->timestamps();
            $table->softDeletes(); // preserva template eliminati per storico wizard

            // Indici
            $table->index('user_id');
            $table->index('scope');
            $table->index(['user_id', 'scope']); // query: "miei template + tutti i globali"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
