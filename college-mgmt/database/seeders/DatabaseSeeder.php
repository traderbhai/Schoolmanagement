<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreUserRoleSeeder::class,
            LegacyCollegeDemoSeeder::class,
            MasterDemoSeeder::class,
        ]);

        $this->command?->info('College Management System seeded successfully!');
        $this->command?->info('Admin: admin@college.com / password');
        $this->command?->info('Teacher: ravi@college.com / password');
        $this->command?->info('Students: aarav@college.com, priya@college.com, rohan@college.com, ... / password');
    }
}
