<?php
// scripts/call_software_index.php

require __DIR__ . '/../vendor/autoload.php';

// Boot the application
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a request to /api/software and request JSON (avoid HTML SPA fallback)
$request = Illuminate\Http\Request::create('/api/software', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

// Try to use a real DB user if present; fall back to a lightweight GenericUser
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
	$user = new Illuminate\Auth\GenericUser([
		'id' => 1,
		'name' => 'script',
		'email' => 'script@example.local',
	]);
	$request->setUserResolver(function() use ($user) { return $user; });
}

$response = $kernel->handle($request);

http_response_code($response->getStatusCode());

$content = $response->getContent();
$ctype = $response->headers->get('Content-Type');

// Print status, content-type and body for quick test
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content-Type: " . ($ctype ?? 'none') . "\n";
echo $content . "\n";

$kernel->terminate($request, $response);

// --- Direct controller invocation (bypass middleware) for testing ---
// This avoids auth:sanctum middleware which would return 401 for unauthenticated requests
try {
	$controllerClass = App\Http\Controllers\Api\Software\SoftwareController::class;
	if (class_exists($controllerClass)) {
		$controller = new $controllerClass();
		$direct = $controller->index($request);

		// If it's a resource collection, convert to response
		if (is_object($direct) && method_exists($direct, 'toResponse')) {
			$directResponse = $direct->toResponse($request);
			echo "\n--- Direct controller response ---\n";
			echo "Status: " . $directResponse->getStatusCode() . "\n";
			echo "Content-Type: " . ($directResponse->headers->get('Content-Type') ?? 'none') . "\n";
			echo $directResponse->getContent() . "\n";
		} elseif ($direct instanceof Symfony\Component\HttpFoundation\Response) {
			echo "\n--- Direct controller response (Response) ---\n";
			echo "Status: " . $direct->getStatusCode() . "\n";
			echo $direct->getContent() . "\n";
		} else {
			echo "\n--- Direct controller returned unexpected type: " . gettype($direct) . "\n";
		}
	}
} catch (Throwable $e) {
	echo "\n--- Direct invocation error: " . $e->getMessage() . "\n";
}
