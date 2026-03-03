<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = $app->make(Illuminate\Database\DatabaseManager::class);
$roles = $db->table('roles')->get();
$modelRoles = $db->table('model_has_roles')->get();
$users = $db->table('users')->where('email','mgastaldello06@gmail.com')->get();
echo "roles:\n";
print_r($roles->toArray());

echo "model_has_roles:\n";
print_r($modelRoles->toArray());

echo "admin user:\n";
print_r($users->toArray());
