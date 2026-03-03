<?php
// database/seeders/UserSeeder.php — Versione più robusta (opzionale)

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1 Admin
        DB::table('users')->insert([
            'nome'          => 'Marco Gastaldello',
            'email'         => 'mgastaldello06@gmail.com',
            'password'      => Hash::make('BW22-flzz'),
            'ruolo'         => 'admin',
            'attivo'        => true,
            'last_login'    => now()->subHours(2),
            'last_login_ip' => '127.0.0.1',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 2 Tecnici (stesso pattern)
        $tecnici = [
            ['Luca Bianchi', 'luca@windeploy.local', 'Tecnico1234!'],
            ['Sara Verdi', 'sara@windeploy.local', 'Tecnico1234!'],
        ];

        foreach ($tecnici as $tecnico) {
            DB::table('users')->insert([
                'nome'          => $tecnico[0],
                'email'         => $tecnico[1],
                'password'      => Hash::make($tecnico[2]),
                'ruolo'         => 'tecnico',
                'attivo'        => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
