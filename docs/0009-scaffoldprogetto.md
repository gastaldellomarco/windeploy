<img src="https://r2cdn.perplexity.ai/pplx-full-logo-primary-dark%402x.png" style="height:64px;margin-right:32px"/>

# Scaffold completo Laravel 11 (PHP 8.3) per WinDeploy su XAMPP Windows.

CARTELLA DESTINAZIONE: C:\xampp\htdocs\windeploy\backend\
 
PACKAGES:

- laravel/sanctum (auth token web app)
- tymon/jwt-auth (JWT per agent Windows)
- spatie/laravel-permission (ruoli: admin/tecnico/viewer)
- CORS: configurazione nativa Laravel 11 (non serve package esterno)
- fakerphp/faker (già incluso, per i Seeder)
 
FORNISCI:

1. Comandi Composer/Artisan per creare il progetto e installare tutto
   (attenzione: su Windows con XAMPP il percorso PHP è C:\xampp\php\php.exe)
2. Struttura cartelle commentata:
   app/Http/Controllers/Api/{Auth, Wizard, Template, Software, Report, Agent, User}
   app/Models/, app/Http/Middleware/, app/Http/Requests/, app/Services/
3. routes/api.php COMPLETO con tutte le route (con commento, senza codice interno):
   - Auth: login, logout, refresh, me
   - Wizards: CRUD + genera codice + monitor
   - Templates: CRUD
   - Software Library: CRUD + toggleActive
   - Reports: lista + dettaglio + download
   - Agent (JWT): auth, start, step, complete, abort
   - Users (admin): CRUD
4. config/cors.php corretto per XAMPP locale:
   allowed_origins: ["http://localhost:5173", "http://windeploy.local"]
5. bootstrap/app.php con middleware TrustProxies registrato
6. Comando per creare il primo utente admin via Seeder o Tinker
 
Codice completo, percorsi Windows, pronto da copiare.

Di seguito trovi uno scaffold completo e **coerente** con Laravel 11 + PHP 8.3 su XAMPP, con Sanctum per la web app, JWT per l’agent e ruoli via Spatie, pronto da copiare in `C:\xampp\htdocs\windeploy\backend\`.

***

## 1. Comandi Composer/Artisan (Laravel 11 + pacchetti)

### 1.1 Creazione progetto Laravel 11 nella cartella richiesta

> Assumo che Composer usi già `C:\xampp\php\php.exe` come PHP CLI; in caso contrario va configurato da Composer o lanciato esplicitamente.

```powershell
# 1) Vai nella cartella padre
cd C:\xampp\htdocs\windeploy\

# 2) Crea un nuovo progetto Laravel 11 nella cartella "backend"
composer create-project laravel/laravel:"^11.0" backend
```

`composer create-project laravel/laravel example-app` è il comando standard per installare Laravel 11, specificando la versione con `^11.0` per forzare Laravel 11 anche se è uscito 12.[^1_1][^1_2]

Poi:

```powershell
cd C:\xampp\htdocs\windeploy\backend

# Copia .env e genera APP_KEY
copy .env.example .env
php artisan key:generate
```


***

### 1.2 Installazione Sanctum per API + SPA React

Laravel 11 usa il comando `php artisan install:api` per installare Sanctum e predisporre l’API scaffolding.[^1_3][^1_4][^1_5][^1_6]

```powershell
# Installa Sanctum e setup API
php artisan install:api
```

Questo comando installa `laravel/sanctum` (v4.x per Laravel 11) e configura le rotte e middleware base per API; in alternativa (se vuoi essere esplicito):[^1_7][^1_8]

```powershell
composer require laravel/sanctum:"^4.0"

# Se necessario, pubblica le migration di Sanctum (Laravel 11+ non le carica più automaticamente)[web:9]
php artisan vendor:publish --tag=sanctum-migrations

# Esegui le migration
php artisan migrate
```


***

### 1.3 Installazione JWT per l’agent Windows (tymon/jwt-auth)

Il package originale `tymon/jwt-auth` si installa così:[^1_9][^1_10][^1_11]

```powershell
composer require tymon/jwt-auth
```

Poi:

```powershell
# Pubblica il file di config jwt.php
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# Genera la JWT_SECRET nel .env
php artisan jwt:secret
```

**Avvocato del diavolo**: `tymon/jwt-auth` è storicamente poco mantenuto sulle ultime versioni di Laravel; molti progetti usano il fork `php-open-source-saver/jwt-auth` per garantire compatibilità con Laravel 11.[^1_11]
Se noti problemi di compatibilità, valuterei seriamente di passare al fork, che espone comandi e config praticamente identici.

***

### 1.4 Installazione Spatie Laravel Permission (ruoli admin/tecnico/viewer)

```powershell
composer require spatie/laravel-permission
```

Poi:

```powershell
# Pubblica config + migration
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Esegui le migration (creano tabelle roles, permissions, ecc.)
php artisan migrate
```

Da progetto WinDeploy, i ruoli necessari sono: `admin`, `tecnico`, `viewer`.[^1_12]

***

### 1.5 Migration DB + seed di base

Dopo avere configurato il DB MySQL in `.env` (come da tue guide XAMPP/WinDeploy), esegui:

```powershell
# Esegue tutte le migration (users, roles/permissions, sanctum, jwt-related se previste, ecc.)
php artisan migrate
```

Se hai seeders personalizzati (es. per dati fake con Faker, già incluso in Laravel 11), puoi lanciare:

```powershell
php artisan db:seed
```


***

## 2. Struttura cartelle commentata (Controllers, Models, Middleware, Requests, Services)

Struttura proposta, coerente con il dominio WinDeploy (wizard, template, software library, report, agent, utenti).[^1_12]

```text
C:\xampp\htdocs\windeploy\backend\
└─ app\
   ├─ Models\
   │  ├─ User.php                 // Utente con ruoli Spatie (admin/tecnico/viewer)
   │  ├─ Wizard.php               // Wizard di configurazione PC
   │  ├─ Template.php             // Template riutilizzabili per wizard
   │  ├─ Software.php             // Entry in software library (winget/custom)
   │  ├─ Report.php               // Report HTML archiviati
   │  ├─ ExecutionLog.php         // Log di esecuzione wizard/agent
   │  └─ AgentToken.php (opz.)    // Ogni eventuale entità legata a agent/JWT
   │
   ├─ Http\
   │  ├─ Controllers\
   │  │  └─ Api\
   │  │     ├─ Auth\
   │  │     │  └─ AuthController.php
   │  │     │     // Gestisce login, logout, refresh, me per la web app (Sanctum)
   │  │     │
   │  │     ├─ Wizard\
   │  │     │  └─ WizardController.php
   │  │     │     // CRUD wizard + generateCode + monitor per stato esecuzione
   │  │     │
   │  │     ├─ Template\
   │  │     │  └─ TemplateController.php
   │  │     │     // CRUD template globali/personali
   │  │     │
   │  │     ├─ Software\
   │  │     │  └─ SoftwareController.php
   │  │     │     // CRUD software library + toggleActive
   │  │     │
   │  │     ├─ Report\
   │  │     │  └─ ReportController.php
   │  │     │     // Lista report, dettaglio, download HTML
   │  │     │
   │  │     ├─ Agent\
   │  │     │  └─ AgentController.php
   │  │     │     // Endpoint JWT per agent: auth, start, step, complete, abort
   │  │     │
   │  │     └─ User\
   │  │        └─ UserController.php
   │  │           // CRUD utenti (solo admin) con ruoli Spatie
   │  │
   │  ├─ Middleware\
   │  │  ├─ Authenticate.php          // Middleware auth standard Laravel
   │  │  ├─ TrustProxies.php          // Proxy middleware (registrato in bootstrap/app.php)
   │  │  ├─ HandleCors.php (core)     // CORS gestito da config/cors.php
   │  │  └─ EnsureFrontendRequestsAreStateful.php (Sanctum)
   │  │     // Garantisce sessioni stateful per SPA su domini configurati in sanctum.php[web:14]
   │  │
   │  ├─ Requests\
   │  │  ├─ Auth\
   │  │  │  └─ LoginRequest.php       // Validazione login web app
   │  │  ├─ Wizard\
   │  │  │  ├─ StoreWizardRequest.php // Validazione create wizard
   │  │  │  └─ UpdateWizardRequest.php
   │  │  ├─ Template\
   │  │  │  └─ StoreTemplateRequest.php
   │  │  ├─ Software\
   │  │  │  └─ StoreSoftwareRequest.php
   │  │  └─ User\
   │  │     └─ StoreUserRequest.php   // Validazione creazione utente admin/tecnico/viewer
   │  │
   │  └─ Services\
   │     ├─ WizardCodeService.php     // Genera codici univoci wizard + scadenza[file:8]
   │     ├─ WizardMonitorService.php  // Polling stato execution logs per monitor real-time[file:8]
   │     ├─ ReportGeneratorService.php// Generazione HTML report lato backend[file:8]
   │     └─ AgentSyncService.php      // Log step agent, cambio stato wizard, ecc.
   │
   └─ ...
```

Checklist rapida per generare i file base:

```powershell
# Controllers API
php artisan make:controller Api/Auth/AuthController
php artisan make:controller Api/Wizard/WizardController --api
php artisan make:controller Api/Template/TemplateController --api
php artisan make:controller Api/Software/SoftwareController --api
php artisan make:controller Api/Report/ReportController --api
php artisan make:controller Api/Agent/AgentController
php artisan make:controller Api/User/UserController --api

# Form Requests (esempi)
php artisan make:request Auth/LoginRequest
php artisan make:request Wizard/StoreWizardRequest
php artisan make:request Wizard/UpdateWizardRequest
php artisan make:request User/StoreUserRequest

# Services (creali come normali classi PHP)
# (li crei a mano in app/Services, oppure con uno schematico tuo)
```


***

## 3. `routes/api.php` completo con tutte le route

File completo, solo definizioni di route + commenti, nessun codice interno dei controller.

Percorso: `C:\xampp\htdocs\windeploy\backend\routes\api.php`

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Wizard\WizardController;
use App\Http\Controllers\Api\Template\TemplateController;
use App\Http\Controllers\Api\Software\SoftwareController;
use App\Http\Controllers\Api\Report\ReportController;
use App\Http\Controllers\Api\Agent\AgentController;
use App\Http\Controllers\Api\User\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Tutte queste rotte sono automaticamente prefissate con /api
| grazie alla configurazione di Laravel 11 in bootstrap/app.php.
|
*/

/*
|--------------------------------------------------------------------------
| Health check API (opzionale, /api/ping)
|--------------------------------------------------------------------------
*/
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

/*
|--------------------------------------------------------------------------
| AUTH (Sanctum) - Login/logout/refresh/me per web app React
| Prefix: /api/auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Login utente (email/password) -> crea sessione/token Sanctum
    Route::post('/login', [AuthController::class, 'login'])
        ->name('auth.login');

    // Logout utente -> revoca token/sessione corrente
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // Refresh token/sessione (se usi un meccanismo di refresh custom)
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware('auth:sanctum')
        ->name('auth.refresh');

    // Restituisce i dati dell'utente autenticato
    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum')
        ->name('auth.me');
});

/*
|--------------------------------------------------------------------------
| API protette per WEB APP (middleware: auth:sanctum)
|--------------------------------------------------------------------------
|
| Tutte le rotte qui dentro richiedono autenticazione web app.
|
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------------------------------------------------------
    | WIZARDS - CRUD + genera codice + monitor
    | Prefix implicito: /api/wizards
    |----------------------------------------------------------------------
    */

    // CRUD completo dei wizard (index, store, show, update, destroy)
    Route::apiResource('wizards', WizardController::class);

    // Genera codice univoco per il wizard (es. WD-7A3F) + scadenza
    Route::post('wizards/{wizard}/generate-code', [WizardController::class, 'generateCode'])
        ->name('wizards.generate-code');

    // Monitor real-time stato esecuzione wizard (polling logs)
    Route::get('wizards/{wizard}/monitor', [WizardController::class, 'monitor'])
        ->name('wizards.monitor');

    /*
    |----------------------------------------------------------------------
    | TEMPLATES - CRUD
    | Prefix: /api/templates
    |----------------------------------------------------------------------
    */

    // CRUD template wizard (globali + personali)
    Route::apiResource('templates', TemplateController::class);

    /*
    |----------------------------------------------------------------------
    | SOFTWARE LIBRARY - CRUD + toggleActive
    | Prefix: /api/software
    |----------------------------------------------------------------------
    */

    // CRUD libreria software (nome, versione, tipo winget/custom, ecc.)
    Route::apiResource('software', SoftwareController::class);

    // Attiva/disattiva una entry software (non viene eliminata fisicamente)
    Route::patch('software/{software}/toggle-active', [SoftwareController::class, 'toggleActive'])
        ->name('software.toggle-active');

    /*
    |----------------------------------------------------------------------
    | REPORTS - lista + dettaglio + download
    | Prefix: /api/reports
    |----------------------------------------------------------------------
    */

    // Lista report con filtri (data, tecnico, stato, ecc.)
    Route::get('reports', [ReportController::class, 'index'])
        ->name('reports.index');

    // Dettaglio singolo report (JSON + eventuale HTML embeddato)
    Route::get('reports/{report}', [ReportController::class, 'show'])
        ->name('reports.show');

    // Download report HTML come file (Content-Disposition: attachment)
    Route::get('reports/{report}/download', [ReportController::class, 'download'])
        ->name('reports.download');

    /*
    |----------------------------------------------------------------------
    | USERS (Admin only) - CRUD
    | Prefix: /api/users
    | Middleware extra: role:admin (Spatie)
    |----------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        // CRUD completo utenti (admin crea/modifica/disattiva utenti)
        Route::apiResource('users', UserController::class);
    });
});

/*
|--------------------------------------------------------------------------
| AGENT (JWT) - Endpoint per eseguibile Windows
| Prefix: /api/agent
|--------------------------------------------------------------------------
|
| Queste rotte sono pensate per l'agent Python (CustomTkinter) che gira
| su Windows e comunica via HTTPS con il backend.
|
*/
Route::prefix('agent')->group(function () {

    // Autenticazione agent -> ritorna JWT per successive chiamate
    Route::post('/auth', [AgentController::class, 'auth'])
        ->name('agent.auth');

    // Rotte protette da JWT (guard "api" con driver jwt)
    Route::middleware('auth:api')->group(function () {

        // Avvio esecuzione wizard su un PC (inizio sessione)
        Route::post('/start', [AgentController::class, 'start'])
            ->name('agent.start');

        // Invio step intermedi (log, stato corrente, percentuale)
        Route::post('/step', [AgentController::class, 'step'])
            ->name('agent.step');

        // Segnala completamento wizard (successo o con errori)
        Route::post('/complete', [AgentController::class, 'complete'])
            ->name('agent.complete');

        // Abort manuale/forzato del wizard sul PC
        Route::post('/abort', [AgentController::class, 'abort'])
            ->name('agent.abort');
    });
});
```

**Rischio principale**: assicurati che il guard `api` in `config/auth.php` usi il driver `jwt` del package tymon, così `auth:api` funziona correttamente per l’agent.[^1_10][^1_9][^1_11]
Per la web app React usa `auth:sanctum` con domini stateful configurati correttamente in `config/sanctum.php`.[^1_13][^1_14][^1_8]

***

## 4. `config/cors.php` per XAMPP locale (localhost:5173 + windeploy.local)

File: `C:\xampp\htdocs\windeploy\backend\config\cors.php`

Laravel usa una config CORS standard con chiavi `paths`, `allowed_origins`, `supports_credentials`, ecc., che va allineata con i domini della tua SPA e con Sanctum.[^1_8]

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths abilitati al CORS
    |--------------------------------------------------------------------------
    |
    | Includiamo tutte le API e la route di Sanctum per il CSRF cookie.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Metodi HTTP consentiti
    |--------------------------------------------------------------------------
    */

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Origini consentite
    |--------------------------------------------------------------------------
    |
    | Ambiente XAMPP locale:
    | - Vite React:  http://localhost:5173
    | - Virtual host: http://windeploy.local
    |
    */

    'allowed_origins' => [
        'http://localhost:5173',
        'http://windeploy.local',
    ],

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Header consentiti
    |--------------------------------------------------------------------------
    */

    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Header esposti
    |--------------------------------------------------------------------------
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache CORS (secondi)
    |--------------------------------------------------------------------------
    */

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Invio credenziali (cookie, Authorization header)
    |--------------------------------------------------------------------------
    |
    | Necessario per SPA + Sanctum con cookie di sessione.
    |
    */

    'supports_credentials' => true,
];
```

**Nota di sicurezza**: con `supports_credentials = true` limita sempre `allowed_origins` a domini specifici e non usare mai `'*'` in produzione, per evitare leakage credenziali via CORS.[^1_8]

***

## 5. `bootstrap/app.php` con `TrustProxies` registrato

File: `C:\xampp\htdocs\windeploy\backend\bootstrap\app.php`

Esempio completo per Laravel 11, con `routes/api.php` e il middleware `TrustProxies` registrato nella sezione `withMiddleware`.[^1_15]

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
        |--------------------------------------------------------------------------
        | Global middleware
        |--------------------------------------------------------------------------
        |
        | Registriamo qui il middleware TrustProxies, così che Laravel
        | gestisca correttamente X-Forwarded-* se in futuro passerai
        | dietro Nginx/Cloudflare (come previsto dal progetto WinDeploy).[file:8]
        |
        */

        $middleware->use([
            TrustProxies::class,
        ]);

        // Qui puoi aggiungere altri middleware globali se necessario
        // es. \Illuminate\Http\Middleware\HandleCors::class, ecc.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Gestione eccezioni personalizzata (se serve)
    })
    ->create();
```

**Nota**: su XAMPP locale `TrustProxies` non è strettamente necessario, ma lo abiliti ora per avere lo stesso comportamento quando sposterai il backend dietro Nginx/HTTPS su Ubuntu 24, come previsto dall’architettura di produzione.[^1_12]

***

## 6. Creare il primo utente admin (Seeder + comando, e alternativa Tinker)

### 6.1 Seeder `AdminUserSeeder` (consigliato per ripetibilità)

1. Crea il seeder:
```powershell
php artisan make:seeder AdminUserSeeder
```

2. Modifica `C:\xampp\htdocs\windeploy\backend\database\seeders\AdminUserSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crea utente admin se non esiste già
        $user = User::firstOrCreate(
            ['email' => 'admin@windeploy.local'],
            [
                'name'     => 'Admin WinDeploy',
                'password' => Hash::make('ChangeThisAdminPassword!'),
            ]
        );

        // Assegna ruolo "admin" (devi aver creato il ruolo prima)
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }
    }
}
```

3. Registra il seeder in `DatabaseSeeder` (opzionale, ma comodo):

`C:\xampp\htdocs\windeploy\backend\database\seeders\DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([
        AdminUserSeeder::class,
    ]);
}
```

4. Esegui il seeder:
```powershell
php artisan db:seed --class=AdminUserSeeder
# oppure, se l'hai aggiunto in DatabaseSeeder:
php artisan db:seed
```

Ora puoi fare login con:

- email: `admin@windeploy.local`
- password: `ChangeThisAdminPassword!`

**Rischio**: non lasciare mai questa password in produzione; usala solo in dev e poi sovrascrivila via UI o Tinker.

***

### 6.2 Alternativa: creazione admin via Tinker (one-shot)

Se preferisci una creazione veloce e manuale:

```powershell
php artisan tinker
```

Dentro Tinker:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$user = User::create([
    'name'     => 'Admin WinDeploy',
    'email'    => 'admin@windeploy.local',
    'password' => Hash::make('ChangeThisAdminPassword!'),
]);

// Se hai configurato Spatie Permission sul modello User
$user->assignRole('admin');
```

Esci con `exit`.

***

## Checklist finale operativa

- [ ] PHP CLI di Composer punta a `C:\xampp\php\php.exe` (versione 8.3).
- [ ] `composer create-project laravel/laravel:"^11.0" backend` eseguito in `C:\xampp\htdocs\windeploy\`.
- [ ] `.env` configurato con DB XAMPP + `APP_URL` coerente con `http://windeploy.local.api` o simile.[^1_14][^1_13]
- [ ] `php artisan install:api` eseguito (Sanctum pronto).[^1_4][^1_5][^1_6][^1_3]
- [ ] `composer require tymon/jwt-auth` + `php artisan jwt:secret` completati.[^1_9][^1_10][^1_11]
- [ ] `composer require spatie/laravel-permission` + publish + migrate eseguiti.
- [ ] Struttura `app/Http/Controllers/Api/...` creata come da albero.
- [ ] `routes/api.php`, `config/cors.php`, `bootstrap/app.php` sostituiti con le versioni sopra.
- [ ] `php artisan migrate` eseguito senza errori.
- [ ] `AdminUserSeeder` eseguito o admin creato via Tinker.

Se vuoi, nel prossimo passo possiamo definire i metodi vuoti dei controller (signature + commenti) o impostare `config/auth.php` per i due guard distinti (Sanctum per web app, JWT per agent) e verificare con curl/PowerShell come nel cheatsheet WinDeploy.[^1_13]
<span style="display:none">[^1_16][^1_17][^1_18][^1_19][^1_20][^1_21][^1_22][^1_23][^1_24][^1_25][^1_26][^1_27][^1_28][^1_29][^1_30][^1_31][^1_32][^1_33][^1_34][^1_35][^1_36]</span>

<div align="center">⁂</div>

[^1_1]: https://dev.to/bdavidxyz/how-to-install-laravel-11-on-linux-29fi

[^1_2]: https://magecomp.com/blog/install-laravel-11-using-composer/

[^1_3]: https://laravel.com/docs/12.x/routing

[^1_4]: https://dev.to/akramghaleb/laravel-11-building-api-using-sanctum-18m

[^1_5]: https://techsolutionstuff.com/post/laravel-11-rest-api-authentication-using-sanctum

[^1_6]: https://github.com/savanihd/Laravel-11-REST-API-using-Sanctum

[^1_7]: https://laravel.com/docs/11.x/upgrade

[^1_8]: https://laravel.com/docs/12.x/sanctum

[^1_9]: https://jwt-auth.readthedocs.io/en/develop/laravel-installation/

[^1_10]: https://dev.to/jruizsilva/laravel-11-api-rest-auth-with-jwt-auth-nb4

[^1_11]: https://blog.logrocket.com/implementing-jwt-authentication-laravel-11/

[^1_12]: 0001-projectdescription.txt

[^1_13]: 0007-cheatsheet.md

[^1_14]: 0005-filedotenv.md

[^1_15]: https://laravel.com/docs/11.x/releases

[^1_16]: 0008-db.md

[^1_17]: 0006-viteconfigjs.md

[^1_18]: 0004-Strutturacartelle.md

[^1_19]: 0003-setupiniziale.md

[^1_20]: 0002-ricerca-iniziale.md

[^1_21]: https://laravel.com/docs/8.x/releases

[^1_22]: https://laravel.com/docs/8.x/upgrade

[^1_23]: https://laravel.com/docs/12.x/homestead

[^1_24]: https://laravel.com/docs/12.x/sail

[^1_25]: https://laravel.com/docs/11.x/starter-kits

[^1_26]: https://cloud.laravel.com/docs/environments

[^1_27]: https://laravel.com/docs/12.x/authentication

[^1_28]: https://herd.laravel.com/docs/macos/changelog

[^1_29]: https://laravel.com/docs/12.x/installation

[^1_30]: https://herd.laravel.com/docs/windows/advanced-usage/command-line

[^1_31]: https://laravel.com/docs/12.x/releases

[^1_32]: https://www.youtube.com/watch?v=RyjXVfPvJ8A

[^1_33]: https://laracasts.com/discuss/channels/laravel/laravel-herd-wont-install-laravel-11

[^1_34]: https://devnook.hashnode.dev/step-by-step-guide-to-building-your-first-laravel-11-project

[^1_35]: https://www.youtube.com/watch?v=ARjpTTjowSw

[^1_36]: https://stackoverflow.com/questions/40815984/how-to-install-all-required-php-extensions-for-laravel

