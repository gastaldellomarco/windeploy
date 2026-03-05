<?php
// File: database/seeders/AdminUserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea l'utente admin iniziale per WinDeploy.
     *
     * ⚠️ SICUREZZA: cambia la password immediatamente dopo il primo accesso.
     * Non committare questo seeder con password reali nel repository.
     * In produzione usa variabili d'ambiente o un vault.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'nome'     => 'Amministratore',
                'email'    => 'admin@windeploy.local',
                'password' => Hash::make('Admin@1234!'),
                'ruolo'    => 'admin',
                'attivo'   => true,
            ]
        );

        $this->command->info('✅ Admin creato: admin@windeploy.local / Admin@1234!');
        $this->command->warn('⚠️  Cambia la password al primo accesso!');
    }
}
