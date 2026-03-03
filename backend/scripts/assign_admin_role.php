<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$email = 'mgastaldello06@gmail.com';
$user = User::where('email', $email)->first();
if (! $user) {
    echo "User with email $email not found\n";
    exit(1);
}

if (method_exists($user, 'assignRole')) {
    $user->assignRole('admin');
    echo "Assigned 'admin' role to {$user->email} (id={$user->id})\n";
} else {
    echo "User model doesn't support assignRole()\n";
}
