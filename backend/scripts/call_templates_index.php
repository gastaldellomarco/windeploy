<?php
require __DIR__ . '/../vendor/autoload.php';

// Boot the application
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/templates', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

// Try to use a real DB user if present; fall back to GenericUser
try {
    $dbUser = null;
    if (class_exists(App\Models\User::class)) {
        $dbUser = App\Models\User::first();
    }
} catch (Throwable $e) {
    $dbUser = null;
}

if ($dbUser) {
    $request->setUserResolver(function() use ($dbUser) { return $dbUser; });
} else {
    $user = new Illuminate\Auth\GenericUser(['id' => 1, 'name' => 'script', 'email' => 'script@example.local']);
    $request->setUserResolver(function() use ($user) { return $user; });
}

// Use kernel to run the request (handles middleware)
$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content-Type: " . ($response->headers->get('Content-Type') ?? 'none') . "\n";
echo $response->getContent() . "\n";

$kernel->terminate($request, $response);

// Direct controller invocation (bypass middleware) for testing
try {
    // Ensure the request has a user resolver for direct invocation
    if (! $request->getUserResolver() || $request->user() === null) {
        $fakeUser = new Illuminate\Auth\GenericUser(['id' => 1, 'ruolo' => 'admin', 'name' => 'script', 'email' => 'script@example.local']);
        $request->setUserResolver(function() use ($fakeUser) { return $fakeUser; });
    }

    $controller = new App\Http\Controllers\Api\Template\TemplateController();
    $direct = $controller->index($request);

    if (is_object($direct) && method_exists($direct, 'toResponse')) {
        $resp = $direct->toResponse($request);
        echo "\n--- Direct controller response ---\n";
        echo "Status: " . $resp->getStatusCode() . "\n";
        echo "Content-Type: " . ($resp->headers->get('Content-Type') ?? 'none') . "\n";
        echo $resp->getContent() . "\n";
    } else {
        echo "Direct call returned " . gettype($direct) . "\n";
    }
} catch (Throwable $e) {
    echo "\nError: " . $e->getMessage() . "\n";
}
