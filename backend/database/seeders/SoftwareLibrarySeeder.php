<?php
// database/seeders/SoftwareLibrarySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SoftwareLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $admin_id = DB::table('users')->where('ruolo', 'admin')->value('id');

        $software = [
            [
                'nome'          => 'Google Chrome',
                'versione'      => 'latest',
                'publisher'     => 'Google LLC',
                'tipo'          => 'winget',
                'identificatore'=> 'Google.Chrome',
                'categoria'     => 'Browser',
                'icona_url'     => 'https://winget.run/img/packages/Google.Chrome.png',
                'aggiunto_da'   => $admin_id,
                'attivo'        => true,
            ],
            [
                'nome'          => 'Mozilla Firefox',
                'versione'      => 'latest',
                'publisher'     => 'Mozilla',
                'tipo'          => 'winget',
                'identificatore'=> 'Mozilla.Firefox',
                'categoria'     => 'Browser',
                'icona_url'     => null,
                'aggiunto_da'   => $admin_id,
                'attivo'        => true,
            ],
            [
                'nome'          => '7-Zip',
                'versione'      => '24.08',
                'publisher'     => 'Igor Pavlov',
                'tipo'          => 'winget',
                'identificatore'=> '7zip.7zip',
                'categoria'     => 'Utilità',
                'icona_url'     => null,
                'aggiunto_da'   => $admin_id,
                'attivo'        => true,
            ],
            [
                'nome'          => 'Microsoft Office 365',
                'versione'      => 'latest',
                'publisher'     => 'Microsoft',
                'tipo'          => 'winget',
                'identificatore'=> 'Microsoft.Office',
                'categoria'     => 'Produttività',
                'icona_url'     => null,
                'aggiunto_da'   => $admin_id,
                'attivo'        => true,
            ],
            [
                'nome'          => 'VLC Media Player',
                'versione'      => '3.0.21',
                'publisher'     => 'VideoLAN',
                'tipo'          => 'winget',
                'identificatore'=> 'VideoLAN.VLC',
                'categoria'     => 'Multimedia',
                'icona_url'     => null,
                'aggiunto_da'   => $admin_id,
                'attivo'        => true,
            ],
        ];

        foreach ($software as $item) {
            DB::table('software_library')->insert(
                array_merge($item, ['created_at' => now()])
            );
        }
    }
}
