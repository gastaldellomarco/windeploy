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
            $table->string('nome', 150);
            $table->text('descrizione')->nullable();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();                          // blocca cancellazione utente con template
            $table->enum('scope', ['globale', 'personale'])->default('personale');
            $table->json('configurazione');                      // struttura identica a wizards.configurazione
            $table->timestamps();                                // created_at + updated_at
            $table->softDeletes();

            // Indici
            $table->index('scope');
            $table->index('user_id');
            $table->index(['scope', 'user_id']);                 // query: "miei template + globali"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
