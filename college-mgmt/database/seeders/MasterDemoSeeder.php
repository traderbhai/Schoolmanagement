<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MasterDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoDataSeeder::class,
            RoleFeatureAccessSeeder::class,
        ]);

        $this->command?->info('Master demo data seeded successfully.');
    }
}
