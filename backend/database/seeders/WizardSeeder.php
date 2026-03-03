<?php
// database/seeders/WizardSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WizardSeeder extends Seeder
{
    public function run(): void
    {
        $tecnico1 = DB::table('users')->where('email', 'luca@windeploy.local')->value('id');
        $tecnico2 = DB::table('users')->where('email', 'sara@windeploy.local')->value('id');

        $wizards = [
            [
                'nome'          => 'Setup Contabilità Standard',
                'user_id'       => $tecnico1,
                'stato'         => 'pronto',
                'nome_pc'       => 'PC-CONT-01',
                'created_offset'=> -2,   // ore fa
            ],
            [
                'nome'          => 'Setup Commerciale Base',
                'user_id'       => $tecnico2,
                'stato'         => 'completato',
                'nome_pc'       => 'PC-COMM-03',
                'created_offset'=> -48,
            ],
            [
                'nome'          => 'Config Direzione Premium',
                'user_id'       => $tecnico1,
                'stato'         => 'bozza',
                'nome_pc'       => 'PC-DIR-01',
                'created_offset'=> -1,
            ],
        ];

        foreach ($wizards as $wiz) {
            $configurazione = [
                'nome_pc'       => $wiz['nome_pc'],
                'utente_admin'  => [
                    'username'           => 'admin-locale',
                    'password_encrypted' => Crypt::encryptString('DevPass123!'),
                ],
                'software_installa' => [
                    ['software_library_id' => 1, 'nome' => 'Google Chrome',  'tipo' => 'winget', 'identificatore' => 'Google.Chrome',    'obbligatorio' => true],
                    ['software_library_id' => 3, 'nome' => '7-Zip',          'tipo' => 'winget', 'identificatore' => '7zip.7zip',         'obbligatorio' => false],
                ],
                'bloatware_default' => [
                    'Microsoft.XboxApp',
                    'king.com.CandyCrushSaga',
                    'Microsoft.BingWeather',
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
            ];

            DB::table('wizards')->insert([
                'nome'           => $wiz['nome'],
                'user_id'        => $wiz['user_id'],
                'template_id'    => null,
                'codice_univoco' => 'WD-' . strtoupper(Str::random(4)),
                'stato'          => $wiz['stato'],
                'configurazione' => json_encode($configurazione),
                'created_at'     => now()->addHours($wiz['created_offset']),
                'expires_at'     => now()->addHours($wiz['created_offset'] + 24),
                'used_at'        => $wiz['stato'] === 'completato' ? now()->addHours($wiz['created_offset'] + 1) : null,
            ]);
        }
    }
}
