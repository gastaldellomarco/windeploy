<?php
// database/migrations/2024_01_01_000003_create_software_library_table.php
// NUOVA — va PRIMA di wizards (anche se non c'è FK fisica, per ordine logico)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('software_library', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255);
            $table->string('versione', 50)->nullable();   // nullable: winget gestisce auto
            $table->string('publisher', 255)->nullable();

            // Tipo determina come l'agent esegue l'installazione:
            // winget → winget install {identificatore}
            // exe/msi → esegue il file dal path {identificatore}
            $table->enum('tipo', ['winget', 'exe', 'msi']);

            // 500 char: winget ID può essere lungo (es. Microsoft.VisualStudioCode)
            // ma anche path UNC \\server\share\setup.exe
            $table->string('identificatore', 500);

            $table->string('categoria', 100)->nullable(); // Browser, Office, Sicurezza, ecc.
            $table->string('icona_url', 500)->nullable();

            // nullOnDelete: se il tecnico che ha aggiunto il software viene rimosso,
            // il software rimane nella libreria (non vogliamo perdere dati)
            $table->foreignId('aggiunto_da')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->boolean('attivo')->default(true);

            // Solo created_at: il software non ha updated_at perché le modifiche
            // creano una nuova versione (approccio immutabile per l'audit trail).
            // Se prevedi edit in-place, aggiungi timestamps() invece.
            $table->timestamps();
            $table->softDeletes(); // soft delete per preservare storico nei report

            // Indici per filtri UI frequenti
            $table->index('tipo');
            $table->index('attivo');
            $table->index('categoria');
            $table->index(['attivo', 'categoria']); // filtro combo più comune nella UI
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('software_library');
    }
};
