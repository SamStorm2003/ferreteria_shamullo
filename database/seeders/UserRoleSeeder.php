<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
   public function run(): void
    {
        $roles = [
            ['name' => 'panel_user', 'guard_name' => 'web'],
            ['name' => 'Vendedor', 'guard_name' => 'web'],
            ['name' => 'Inventario', 'guard_name' => 'web'],
            ['name' => 'Super Admin', 'guard_name' => 'web'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
        $users = [
            [
                'name' => 'Joel Andres Chura',
                'email' => 'andreusjhoel67@gmail.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'roles' => ['Super Admin', 'panel_user'],
            ],
            [
                'name' => 'Luis Villca Mamani',
                'email' => 'luisvillca@gmail.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'roles' => ['Vendedor'],
            ],
            [
                'name' => 'Shamir Condori Troche',
                'email' => 'inventario@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'roles' => ['Inventario'],
            ],
        ];

        foreach ($users as $userData) {
            $roles = $userData['roles'];
            unset($userData['roles']);
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            $user->syncRoles($roles);
        }
    }
}
