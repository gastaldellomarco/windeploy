<?php
// database/migrations/2026_03_06_000001_add_security_fields_to_wizards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge i campi di sicurezza alla tabella wizards.
     *
     * - attempt_count: contatore atomico dei tentativi MAC falliti (max 3 → locked)
     * - last_attempt_ip: IP dell'ultimo tentativo fallito, utile per audit e incident response
     *
     * NOTA: used_at esiste già nella migration originale createwizardstable.php.
     * Non viene ripetuto qui per evitare errori "Duplicate column name" su migratefresh.
     */
    public function up(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Contatore tentativi falliti: incrementato atomicamente con Eloquent increment()
            // per evitare race condition su richieste parallele dallo stesso agent.
            // TinyInteger non-signed: range 0-255, più che sufficiente per il limite di 3.
            $table->unsignedTinyInteger('attempt_count')
                  ->default(0)
                  ->after('used_at');

            // IP dell'ultimo tentativo fallito: 45 caratteri coprono indirizzi IPv6 completi.
            // Nullable perché popolato solo in caso di MAC mismatch (non al primo accesso valido).
            $table->string('last_attempt_ip', 45)
                  ->nullable()
                  ->after('attempt_count');

            // Indice per query di audit: "tutti i wizard bloccati nell'ultimo giorno"
            // SELECT * FROM wizards WHERE attempt_count >= 3 AND updated_at > NOW() - INTERVAL 1 DAY
            $table->index('attempt_count', 'idx_wizards_attempt_count');
        });
    }

    /**
     * Ripristina lo stato originale della tabella rimuovendo i campi aggiunti.
     */
    public function down(): void
    {
        Schema::table('wizards', function (Blueprint $table) {
            // Rimuovere prima l'indice, poi la colonna (MySQL richiede questo ordine)
            $table->dropIndex('idx_wizards_attempt_count');
            $table->dropColumn(['attempt_count', 'last_attempt_ip']);
        });
    }
};
