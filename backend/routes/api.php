<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Wizard\WizardController;
use App\Http\Controllers\Api\Template\TemplateController;
use App\Http\Controllers\Api\Software\SoftwareController;
use App\Http\Controllers\Api\Report\ReportController;
use App\Http\Controllers\Api\Agent\AgentController;
use App\Http\Controllers\Api\Agent\AgentAuthController;
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

/* Health check and lightweight stats */
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/stats', function () {
    return response()->json([
        'pc_mese' => 0,
        'wizard_attivi' => 0,
        'software_top' => null,
        'errori' => 0,
        'grafico_settimanale' => [],
    ]);
});

/* AUTH (Sanctum) */
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum')->name('auth.refresh');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum')->name('auth.me');
});

/* Protected web app routes (auth:sanctum) */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/debug/me', function (Request $request) {
        $user = $request->user();

        if (! $user) {
            return response()->json(['user' => null], 200);
        }

        return response()->json([
            'user' => $user->only(['id', 'nome', 'email', 'ruolo']),
            'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [],
        ]);
    });

    // Wizards CRUD + extras
    Route::apiResource('wizards', WizardController::class);
    Route::post('wizards/{wizard}/generate-code', [WizardController::class, 'generateCode'])->name('wizards.generate-code');
    Route::get('wizards/{wizard}/monitor', [WizardController::class, 'monitor'])->name('wizards.monitor');

    // Templates accessible to admin + tecnico, but disallow destroy via API for safety
    Route::middleware('role:admin,tecnico')->group(function () {
        Route::apiResource('templates', TemplateController::class)->except(['destroy']);
    });

    // Software and users: admin only
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('software', SoftwareController::class);
        Route::patch('software/{software}/toggle-active', [SoftwareController::class, 'toggleActive'])->name('software.toggle-active');
        Route::apiResource('users', UserController::class);
    });

    // Reports (list, show, download)
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
});

/* AGENT (JWT) - Endpoint per eseguibile Windows */
Route::prefix('agent')->group(function () {
    // ──────────────────────────────────────────────────────────────────────
    // Route pubblica per autenticazione agent: throttle:agent_auth applicato
    // SOLO a questo endpoint perché è l'unico senza JWT.
    //
    // IMPORTANTE: NON applicare throttle:agent_auth alle route /agent/*
    // successive (step, complete, ecc.) che sono già protette da JWT e
    // usano il limiter 'agent' separato.
    // ──────────────────────────────────────────────────────────────────────
    Route::post('/auth', [AgentAuthController::class, 'auth'])->middleware('throttle:agent_auth')->name('agent.auth');

    Route::middleware(['auth:api', 'throttle:agent'])->group(function () {
        Route::post('/start', [AgentController::class, 'start'])->name('agent.start');
        Route::post('/step', [\App\Http\Controllers\Api\Agent\AgentStepController::class, 'step'])->name('agent.step');
        Route::post('/complete', [AgentController::class, 'complete'])->name('agent.complete');
        Route::post('/abort', [AgentController::class, 'abort'])->name('agent.abort');
    });
});
