<?php
// database/migrations/2024_01_01_000001_create_users_table.php
// VERSIONE DEFINITIVA — sostituisce quella esistente

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('email')->unique();
            $table->string('password');

            // Ruolo gestito come enum nativo MySQL per performance e constraint DB-level.
            // Spatie permission usa tabelle separate — manteniamo l'enum come source of truth
            // per query veloci (WHERE ruolo = 'admin') senza JOIN.
            $table->enum('ruolo', ['admin', 'tecnico', 'viewer'])->default('tecnico');

            $table->boolean('attivo')->default(true);

            // timestamps() genera sia created_at che updated_at — standard Laravel
            $table->timestamps();

            // Audit fields per sicurezza e compliance
            $table->timestamp('last_login')->nullable();
            $table->string('last_login_ip', 45)->nullable(); // 45 char coprono IPv6 completo

            // SoftDeletes: un utente eliminato ha wizard e log storici da preservare.
            // restrictOnDelete sulle FK garantisce che non si possa cancellare
            // un utente con dati collegati senza prima riassegnarli.
            $table->softDeletes();

            // Indici per query frequenti
            $table->index('ruolo');
            $table->index('attivo');
            $table->index(['email', 'attivo']); // query login: email + attivo insieme
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
