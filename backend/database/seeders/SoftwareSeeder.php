<?php
// database/seeders/SoftwareSeeder.php

namespace Database\Seeders;

use App\Models\SoftwareLibrary;
use App\Models\User;
use Illuminate\Database\Seeder;

class SoftwareSeeder extends Seeder
{
    public function run(): void
    {
        // Recupera l'ID dell'admin — aggiunto_da non può essere NULL qui
        $adminId = User::where('email', 'admin@windeploy.local')->value('id');

        // Dati reali con ID winget verificati al 2026-03.
        // Fonte: winget.run e Microsoft winget-pkgs su GitHub.
        $software = [
            [
                'nome'           => 'Google Chrome',
                'versione'       => null, // winget installa sempre latest
                'publisher'      => 'Google LLC',
                'tipo'           => 'winget',
                'identificatore' => 'Google.Chrome',
                'categoria'      => 'Browser',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Mozilla Firefox',
                'versione'       => null,
                'publisher'      => 'Mozilla',
                'tipo'           => 'winget',
                'identificatore' => 'Mozilla.Firefox',
                'categoria'      => 'Browser',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => '7-Zip',
                'versione'       => '24.08',
                'publisher'      => 'Igor Pavlov',
                'tipo'           => 'winget',
                'identificatore' => '7zip.7zip',
                'categoria'      => 'Utilità',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'VLC Media Player',
                'versione'       => '3.0.21',
                'publisher'      => 'VideoLAN',
                'tipo'           => 'winget',
                'identificatore' => 'VideoLAN.VLC',
                'categoria'      => 'Multimedia',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Notepad++',
                'versione'       => '8.7',
                'publisher'      => 'Notepad++ Team',
                'tipo'           => 'winget',
                'identificatore' => 'Notepad++.Notepad++',
                'categoria'      => 'Sviluppo',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Adobe Acrobat Reader',
                'versione'       => null,
                'publisher'      => 'Adobe Inc.',
                'tipo'           => 'winget',
                'identificatore' => 'Adobe.Acrobat.Reader.64-bit',
                'categoria'      => 'Office',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'Microsoft Visual C++ Redistributable',
                'versione'       => null,
                'publisher'      => 'Microsoft Corporation',
                'tipo'           => 'winget',
                'identificatore' => 'Microsoft.VCRedist.2015+.x64',
                'categoria'      => 'Sistema',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => true,
            ],
            [
                'nome'           => 'WinRAR',
                'versione'       => '7.10',
                'publisher'      => 'win.rar GmbH',
                'tipo'           => 'winget',
                'identificatore' => 'RARLab.WinRAR',
                'categoria'      => 'Utilità',
                'icona_url'      => null,
                'aggiunto_da'    => $adminId,
                'attivo'         => false, // disattivato: preferire 7-Zip (free)
            ],
        ];

        foreach ($software as $item) {
            SoftwareLibrary::firstOrCreate(
                ['identificatore' => $item['identificatore']],
                $item
            );
        }
    }
}
