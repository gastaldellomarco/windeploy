<?php
// database/migrations/2024_01_01_000001_create_users_table.php — CORRETTA

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
            $table->enum('ruolo', ['admin', 'tecnico', 'viewer'])->default('tecnico');
            $table->boolean('attivo')->default(true);
            
            // ✅ AGGIUNTO: timestamps standard Laravel
            $table->timestamps();  // created_at + updated_at
            
            $table->timestamp('last_login')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->softDeletes();  // deleted_at

            // Indici
            $table->index('ruolo');
            $table->index('attivo');
            $table->index(['email', 'attivo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
