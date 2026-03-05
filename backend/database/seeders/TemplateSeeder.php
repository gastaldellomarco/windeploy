<?php
// database/seeders/TemplateSeeder.php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $adminId    = User::where('email', 'admin@windeploy.local')->value('id');
        $tecnico1Id = User::where('email', 'tecnico1@windeploy.local')->value('id');

        // Template globale (creato dall'admin, visibile a tutti i tecnici)
        Template::firstOrCreate(
            ['nome' => 'Standard Aziendale'],
            [
                'descrizione'   => 'Configurazione base per tutti i PC aziendali. Include browser, PDF reader e utilità.',
                'user_id'       => $adminId,
                'scope'         => 'globale',
                'configurazione' => [
                    'software_installa' => [
                        ['software_library_id' => 1, 'nome' => 'Google Chrome',         'tipo' => 'winget', 'identificatore' => 'Google.Chrome',                  'obbligatorio' => true],
                        ['software_library_id' => 3, 'nome' => '7-Zip',                 'tipo' => 'winget', 'identificatore' => '7zip.7zip',                      'obbligatorio' => true],
                        ['software_library_id' => 6, 'nome' => 'Adobe Acrobat Reader',  'tipo' => 'winget', 'identificatore' => 'Adobe.Acrobat.Reader.64-bit',    'obbligatorio' => true],
                        ['software_library_id' => 7, 'nome' => 'VC++ Redistributable',  'tipo' => 'winget', 'identificatore' => 'Microsoft.VCRedist.2015+.x64',   'obbligatorio' => true],
                    ],
                    'bloatware_default' => [
                        'Microsoft.XboxApp',
                        'king.com.CandyCrushSaga',
                        'Microsoft.BingWeather',
                        'Microsoft.OneDriveSync',
                        'MicrosoftTeams',
                    ],
                    'power_plan' => [
                        'tipo'   => 'preset',
                        'params' => ['preset' => 'balanced'],
                    ],
                    'extras' => [
                        'timezone'                 => 'Europe/Rome',
                        'language'                 => 'it-IT',
                        'keyboard_layout'          => 'it-IT',
                        'wallpaper_url'            => null,
                        'wifi'                     => null,
                        'windows_update'           => ['policy' => 'download_only'],
                        'remove_microsoft_account' => true,
                    ],
                ],
            ]
        );

        // Template personale del tecnico1 (ottimizzato per sviluppatori)
        Template::firstOrCreate(
            ['nome' => 'Dev Workstation'],
            [
                'descrizione'   => 'Template personale per postazioni sviluppo. VLC, Notepad++, Chrome e strumenti di base.',
                'user_id'       => $tecnico1Id,
                'scope'         => 'personale',
                'configurazione' => [
                    'software_installa' => [
                        ['software_library_id' => 1, 'nome' => 'Google Chrome', 'tipo' => 'winget', 'identificatore' => 'Google.Chrome',        'obbligatorio' => true],
                        ['software_library_id' => 5, 'nome' => 'Notepad++',     'tipo' => 'winget', 'identificatore' => 'Notepad++.Notepad++',   'obbligatorio' => true],
                        ['software_library_id' => 3, 'nome' => '7-Zip',         'tipo' => 'winget', 'identificatore' => '7zip.7zip',             'obbligatorio' => false],
                        ['software_library_id' => 4, 'nome' => 'VLC',           'tipo' => 'winget', 'identificatore' => 'VideoLAN.VLC',          'obbligatorio' => false],
                    ],
                    'bloatware_default' => [
                        'Microsoft.XboxApp',
                        'king.com.CandyCrushSaga',
                        'Microsoft.BingWeather',
                    ],
                    'power_plan' => [
                        'tipo'   => 'preset',
                        'params' => ['preset' => 'high_performance'],
                    ],
                    'extras' => [
                        'timezone'                 => 'Europe/Rome',
                        'language'                 => 'it-IT',
                        'keyboard_layout'          => 'it-IT',
                        'wallpaper_url'            => null,
                        'wifi'                     => null,
                        'windows_update'           => ['policy' => 'manual'],
                        'remove_microsoft_account' => true,
                    ],
                ],
            ]
        );
    }
}
