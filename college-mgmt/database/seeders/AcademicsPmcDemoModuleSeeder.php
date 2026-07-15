<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AcademicsPmcDemoModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AcademicsOperatingDemoSeeder::class);
    }
}
