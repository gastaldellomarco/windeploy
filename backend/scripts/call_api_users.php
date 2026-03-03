<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a request to /api/users with Sanctum cookie/session is not present; but route is protected by auth:sanctum + role:admin.
// For quick local test, we'll temporarily disable middleware by calling controller directly.

$controller = new App\Http\Controllers\Api\User\UserController();
$response = $controller->index();

// If response is a JsonResponse, dump content
if (method_exists($response, 'getContent')) {
    echo $response->getContent();
} else {
    var_dump($response);
}
