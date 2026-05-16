<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$app->boot();

use Spatie\Permission\Models\Role;
use App\Models\User;

$role = Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'sanctum']);
echo "Role ensured: ID " . $role->id . PHP_EOL;

$users = User::all();
foreach ($users as $user) {
    if (!$user->hasRole('Customer')) {
        $user->assignRole('Customer');
        echo "Assigned Customer role to: " . $user->email . PHP_EOL;
    } else {
        echo "Already has role: " . $user->email . PHP_EOL;
    }
}

echo "Done!" . PHP_EOL;
