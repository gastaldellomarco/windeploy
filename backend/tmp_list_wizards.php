<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Wizard;

$wizard = Wizard::first();
if (!$wizard) {
    echo "NO_WIZARD\n";
    exit(0);
}

echo "WIZARD:" . $wizard->id . ":" . $wizard->codice_univoco . "\n";
