<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "ENV JWT_SECRET=" . (getenv('JWT_SECRET') ?: '(none)') . PHP_EOL;
echo "ENV JWT_TTL=" . (getenv('JWT_TTL') ?: '(none)') . PHP_EOL;
echo "ENV JWT_REFRESH_TTL=" . (getenv('JWT_REFRESH_TTL') ?: '(none)') . PHP_EOL;
echo "CONFIG jwt ttl type=" . gettype(config('jwt.ttl')) . " value=" . var_export(config('jwt.ttl'), true) . PHP_EOL;
echo "CONFIG jwt refresh_ttl type=" . gettype(config('jwt.refresh_ttl')) . " value=" . var_export(config('jwt.refresh_ttl'), true) . PHP_EOL;
echo "CONFIG jwt keys public=" . var_export(config('jwt.keys.public'), true) . PHP_EOL;
echo "CONFIG jwt secret=" . var_export(config('jwt.secret'), true) . PHP_EOL;

// show user model class implements JWTSubject?
$userClass = App\Models\User::class;
echo "User class: $userClass implements JWTSubject? ";
$implements = in_array('Tymon\\JWTAuth\\Contracts\\JWTSubject', class_implements($userClass) ?: []);
echo $implements ? 'yes' : 'no';
echo PHP_EOL;
