<?php
// database/migrations/2024_01_01_000004_create_software_library_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_library', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            $table->string('versione', 50)->nullable();          // es. "120.0.6099.130", nullable per winget auto
            $table->string('publisher', 150)->nullable();
            $table->enum('tipo', ['winget', 'exe', 'msi']);
            $table->string('identificatore', 255);               // winget ID o path relativo file
            $table->string('categoria', 100)->nullable();        // es. "Browser", "Office", "Sicurezza"
            $table->string('icona_url', 500)->nullable();
            $table->foreignId('aggiunto_da')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->boolean('attivo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            // Indici
            $table->index('attivo');
            $table->index('categoria');
            $table->index('tipo');
            $table->index(['attivo', 'categoria']);               // filtro frequente nella UI
            $table->fullText('nome');                             // ricerca testuale software (MySQL 8 InnoDB)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_library');
    }
};
