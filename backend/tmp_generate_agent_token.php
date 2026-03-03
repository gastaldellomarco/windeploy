<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Carbon\Carbon;

$wizardId = 1;
$mac = 'AA:BB:CC:DD:EE:FF';

$now = Carbon::now();
$expiry = $now->copy()->addHours(4);

$payload = JWTFactory::customClaims([
    'sub' => $wizardId,
    'wizard_id' => $wizardId,
    'mac_address' => strtolower($mac),
    'type' => 'agent',
    'iat' => $now->timestamp,
    'exp' => $expiry->timestamp,
])->make();

$token = JWTAuth::encode($payload)->get();

echo $token . PHP_EOL;
