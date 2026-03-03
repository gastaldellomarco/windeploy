<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\User\UserController;

$payload = [
    'nome' => 'Test User',
    'email' => 'testuser@windeploy.local',
    'ruolo' => 'viewer',
    'password_temporanea' => 'Temporary123',
];

$request = Request::create('/api/users', 'POST', $payload, [], [], ['REMOTE_ADDR' => '10.0.0.99']);

$controller = new UserController();
$response = $controller->store($request);

if (method_exists($response, 'getContent')) {
    echo $response->getContent() . PHP_EOL;
} else {
    var_dump($response);
}

// List last 5 users
$db = $app->make(Illuminate\Database\DatabaseManager::class);
$users = $db->table('users')->orderBy('id','desc')->limit(5)->get();
print_r($users->toArray());
