<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CoreUserRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'teacher', 'student'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $admin = User::firstOrCreate(['email' => 'admin@college.com'], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
        ]);

        $admin->assignRole('admin');
    }
}
