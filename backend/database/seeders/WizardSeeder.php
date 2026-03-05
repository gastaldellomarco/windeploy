<?php
// database/seeders/WizardSeeder.php

namespace Database\Seeders;

use App\Models\ExecutionLog;
use App\Models\Report;
use App\Models\Template;
use App\Models\User;
use App\Models\Wizard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class WizardSeeder extends Seeder
{
    public function run(): void
    {
        $tecnico1Id  = User::where('email', 'tecnico1@windeploy.local')->value('id');
        $tecnico2Id  = User::where('email', 'tecnico2@windeploy.local')->value('id');
        $templateGlobId = Template::where('nome', 'Standard Aziendale')->value('id');

        // Helper per generare configurazione JSON realistica
        $buildConfig = function (string $nomePc, array $extraSoftware = []): array {
            $softwareBase = [
                ['software_library_id' => 1, 'nome' => 'Google Chrome',        'tipo' => 'winget', 'identificatore' => 'Google.Chrome',                'obbligatorio' => true],
                ['software_library_id' => 3, 'nome' => '7-Zip',                'tipo' => 'winget', 'identificatore' => '7zip.7zip',                    'obbligatorio' => true],
                ['software_library_id' => 6, 'nome' => 'Adobe Acrobat Reader', 'tipo' => 'winget', 'identificatore' => 'Adobe.Acrobat.Reader.64-bit',  'obbligatorio' => true],
            ];

            return [
                'nome_pc'      => $nomePc,
                'utente_admin' => [
                    'username'           => 'admin-locale',
                    // La password viene sempre cifrata — mai in chiaro nel DB
                    'password_encrypted' => Crypt::encryptString('DevLocal@2026!'),
                ],
                'software_installa' => array_merge($softwareBase, $extraSoftware),
                'bloatware_default' => [
                    'Microsoft.XboxApp',
                    'king.com.CandyCrushSaga',
                    'Microsoft.BingWeather',
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
            ];
        };

        // ── Wizard 1: BOZZA (tecnico1, nessun template) ──────────────────────
        Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-AAAA'],
            [
                'nome'           => 'PC Contabilità 01 - Bozza',
                'user_id'        => $tecnico1Id,
                'template_id'    => null,
                'codice_univoco' => 'WD-AAAA',
                'stato'          => 'bozza',
                'configurazione' => $buildConfig('PC-CONT-01'),
                'expires_at'     => now()->addHours(20),
                'used_at'        => null,
            ]
        );

        // ── Wizard 2: PRONTO (tecnico1, da template globale) ─────────────────
        Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-BBBB'],
            [
                'nome'           => 'PC Ufficio Commerciale 03',
                'user_id'        => $tecnico1Id,
                'template_id'    => $templateGlobId,
                'codice_univoco' => 'WD-BBBB',
                'stato'          => 'pronto',
                'configurazione' => $buildConfig('PC-COMM-03', [
                    ['software_library_id' => 4, 'nome' => 'VLC Media Player', 'tipo' => 'winget', 'identificatore' => 'VideoLAN.VLC', 'obbligatorio' => false],
                ]),
                'expires_at'     => now()->addHours(18),
                'used_at'        => null,
            ]
        );

        // ── Wizard 3: COMPLETATO (tecnico2) con execution_log e report ───────
        $wizard3 = Wizard::firstOrCreate(
            ['codice_univoco' => 'WD-CCCC'],
            [
                'nome'           => 'Postazione Direzione Premium',
                'user_id'        => $tecnico2Id,
                'template_id'    => null,
                'codice_univoco' => 'WD-CCCC',
                'stato'          => 'completato',
                'configurazione' => $buildConfig('PC-DIR-01', [
                    ['software_library_id' => 5, 'nome' => 'Notepad++', 'tipo' => 'winget', 'identificatore' => 'Notepad++.Notepad++', 'obbligatorio' => false],
                ]),
                'expires_at'     => now()->subHours(20), // già scaduto (usato)
                'used_at'        => now()->subHours(22),
            ]
        );

        // Crea execution_log per il wizard completato (se non esiste già)
        $log = ExecutionLog::firstOrCreate(
            ['wizard_id' => $wizard3->id],
            [
                'pc_nome_originale' => 'DESKTOP-XKJF982',
                'pc_nome_nuovo'     => 'PC-DIR-01',
                'tecnico_user_id'   => $tecnico2Id,
                'hardware_info'     => [
                    'cpu'             => 'Intel Core i7-12700',
                    'ram_gb'          => 32,
                    'disco_gb'        => 1024,
                    'windows_version' => 'Windows 11 Pro 23H2',
                ],
                'stato' => 'completato',
                'log_dettagliato' => [
                    ['step' => 'rename_pc',         'timestamp' => now()->subHours(22)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => 'PC rinominato da DESKTOP-XKJF982 a PC-DIR-01'],
                    ['step' => 'create_admin_user', 'timestamp' => now()->subHours(22)->addMinutes(1)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => 'Utente admin-locale creato'],
                    ['step' => 'remove_bloatware',  'timestamp' => now()->subHours(22)->addMinutes(3)->toIso8601String(), 'esito' => 'ok',    'dettaglio' => '4 app rimosse'],
                    ['step' => 'install_software',  'timestamp' => now()->subHours(22)->addMinutes(10)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => '4 software installati via winget'],
                    ['step' => 'power_plan',        'timestamp' => now()->subHours(22)->addMinutes(15)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Piano alimentazione: balanced'],
                    ['step' => 'timezone_language', 'timestamp' => now()->subHours(22)->addMinutes(16)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Timezone: Europe/Rome, Language: it-IT'],
                    ['step' => 'windows_update',    'timestamp' => now()->subHours(22)->addMinutes(18)->toIso8601String(), 'esito' => 'ok',   'dettaglio' => 'Policy impostata: download_only'],
                ],
                'started_at'   => now()->subHours(22),
                'completed_at' => now()->subHours(22)->addMinutes(20),
            ]
        );

        // Crea report HTML per il log (se non esiste già)
        Report::firstOrCreate(
            ['execution_log_id' => $log->id],
            [
                'html_content' => $this->buildHtmlReport($log),
            ]
        );
    }

    // Genera un report HTML minimale ma realistico per il seeder
    private function buildHtmlReport(ExecutionLog $log): string
    {
        $steps = collect($log->log_dettagliato)
            ->map(fn($s) => "<tr><td>{$s['step']}</td><td>{$s['esito']}</td><td>{$s['dettaglio']}</td></tr>")
            ->implode("\n");

        return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><title>Report WinDeploy - {$log->pc_nome_nuovo}</title>
<style>body{font-family:Arial,sans-serif;padding:20px} table{width:100%;border-collapse:collapse} td,th{border:1px solid #ccc;padding:8px} th{background:#2563eb;color:#fff}</style>
</head>
<body>
  <h1>Report Configurazione PC</h1>
  <p><strong>PC:</strong> {$log->pc_nome_originale} → {$log->pc_nome_nuovo}</p>
  <p><strong>Stato:</strong> {$log->stato}</p>
  <p><strong>Inizio:</strong> {$log->started_at}</p>
  <p><strong>Fine:</strong> {$log->completed_at}</p>
  <h2>Step eseguiti</h2>
  <table><thead><tr><th>Step</th><th>Esito</th><th>Dettaglio</th></tr></thead>
  <tbody>{$steps}</tbody></table>
  <p><em>Generato da WinDeploy</em></p>
</body></html>
HTML;
    }
}
