<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Usiamo firstOrCreate per rendere il seeder idempotente:
        // se girato due volte non duplica gli utenti.

        // Admin principale
        User::firstOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'nome'          => 'Admin WinDeploy',
                'password'      => Hash::make('Admin@1234!'),
                'ruolo'         => 'admin',
                'attivo'        => true,
                'last_login'    => now()->subHours(3),
                'last_login_ip' => '127.0.0.1',
            ]
        );

        // Tecnico 1 — proprietario dei template e wizard nel seeder
        User::firstOrCreate(
            ['email' => 'tecnico1@windeploy.local'],
            [
                'nome'          => 'Marco Ferretti',
                'password'      => Hash::make('Tecnico@1234!'),
                'ruolo'         => 'tecnico',
                'attivo'        => true,
                'last_login'    => now()->subDays(1),
                'last_login_ip' => '192.168.1.10',
            ]
        );

        // Tecnico 2
        User::firstOrCreate(
            ['email' => 'tecnico2@windeploy.local'],
            [
                'nome'          => 'Sara Lombardi',
                'password'      => Hash::make('Tecnico@1234!'),
                'ruolo'         => 'tecnico',
                'attivo'        => true,
                'last_login'    => now()->subDays(3),
                'last_login_ip' => '192.168.1.11',
            ]
        );

        // Viewer (solo lettura — accede ai report)
        User::firstOrCreate(
            ['email' => 'viewer@windeploy.local'],
            [
                'nome'     => 'Responsabile IT',
                'password' => Hash::make('Viewer@1234!'),
                'ruolo'    => 'viewer',
                'attivo'   => true,
            ]
        );
    }
}
