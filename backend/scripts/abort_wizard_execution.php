<?php
// Small script to mark in-progress execution logs for a given wizard as aborted.
// Usage: php scripts/abort_wizard_execution.php <wizard_id>

require __DIR__ . '/../vendor/autoload.php';

$wizardId = $argv[1] ?? 1;

$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ExecutionLog;

$log = ExecutionLog::where('wizard_id', $wizardId)
    ->whereIn('stato', ['avviato', 'in_corso'])
    ->first();

if (! $log) {
    echo "No active execution found for wizard_id={$wizardId}\n";
    exit(0);
}

$log->stato = 'abortito';
$log->completed_at = now();
$log->save();

echo "Marked execution_log id={$log->id} for wizard_id={$wizardId} as abortito\n";
