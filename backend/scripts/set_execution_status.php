<?php
// Usage: php set_execution_status.php <execution_log_id> <status>
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ExecutionLog;

$execId = isset($argv[1]) ? intval($argv[1]) : null;
$status = $argv[2] ?? 'abortito';

if (! $execId) {
    echo "Usage: php set_execution_status.php <execution_log_id> <status>\n";
    exit(1);
}

$log = ExecutionLog::find($execId);
if (! $log) {
    echo "ExecutionLog id={$execId} not found.\n";
    exit(1);
}

$log->stato = $status;
$log->completed_at = now();
$log->save();

echo "Set execution_log id={$execId} stato={$status}\n";
