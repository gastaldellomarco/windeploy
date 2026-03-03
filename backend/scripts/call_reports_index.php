<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\Report\ReportController;

$controller = new ReportController();
$request = Request::create('/api/reports', 'GET', []);
// Simulate an authenticated admin user for testing
$request->setUserResolver(function () {
    $u = new stdClass();
    $u->id = 1;
    $u->ruolo = 'admin';
    return $u;
});

$response = $controller->index($request);

if (method_exists($response, 'toJson')) {
    echo $response->toJson();
} elseif (method_exists($response, 'getContent')) {
    echo $response->getContent();
} else {
    var_dump($response);
}
