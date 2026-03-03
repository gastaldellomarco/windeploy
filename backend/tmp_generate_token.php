<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$user = App\Models\User::find(1);
if (! $user) {
    echo "NO_USER\n";
    exit(1);
}
// Ensure config values used by tymon/jwt-auth are integers to avoid Carbon errors
config(['jwt.ttl' => (int) config('jwt.ttl')]);
config(['jwt.refresh_ttl' => (int) config('jwt.refresh_ttl')]);

$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
echo $token . PHP_EOL;
