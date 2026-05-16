<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class FixCustomerRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure Customer role exists
        $role = Role::firstOrCreate([
            'name'       => 'Customer',
            'guard_name' => 'sanctum',
        ]);

        $this->command->info("Customer role ID: {$role->id}");

        // Assign to all users missing the role
        User::all()->each(function (User $user) {
            if (!$user->hasRole('Customer')) {
                $user->assignRole('Customer');
                $this->command->info("Assigned to: {$user->email}");
            }
        });

        $this->command->info('Done!');
    }
}
