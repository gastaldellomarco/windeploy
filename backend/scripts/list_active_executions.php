<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ExecutionLog;

$active = ExecutionLog::whereIn('stato', ['avviato', 'in_corso'])->get();
if ($active->isEmpty()) {
    echo "No active execution logs found.\n";
    exit(0);
}

foreach ($active as $log) {
    echo "id={$log->id} wizard_id={$log->wizard_id} stato={$log->stato} started_at={$log->started_at} completed_at={$log->completed_at}\n";
}
