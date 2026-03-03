<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\User\UserController;

$controller = new UserController();

// Reset password for user id 5 (testuser) created earlier
$request = Request::create('/api/users/5', 'PUT', ['action' => 'reset_password']);
$response = $controller->update($request, '5');
if (method_exists($response, 'getContent')) {
    echo "Reset response: " . $response->getContent() . PHP_EOL;
} else { var_dump($response); }

// Now delete the user
$response2 = $controller->destroy('5');
if (is_null($response2) || method_exists($response2, 'getContent')) {
    echo "Delete response: ";
    if (method_exists($response2, 'getStatusCode')) echo $response2->getStatusCode();
    echo PHP_EOL;
} else { var_dump($response2); }

// Check users
$db = $app->make(Illuminate\Database\DatabaseManager::class);
$users = $db->table('users')->orderBy('id','desc')->limit(10)->get();
print_r($users->toArray());
