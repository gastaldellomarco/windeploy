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
        ->middleware('throttle:login') // 5 tentativi
        ->name('agent.auth');

    // Rotte protette da JWT (guard "api" con driver jwt)
    Route::middleware(['auth:api', 'throttle:agent'])->group(function () {

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
