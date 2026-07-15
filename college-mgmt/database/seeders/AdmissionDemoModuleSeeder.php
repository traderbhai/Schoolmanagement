<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdmissionDemoModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdmissionOperatingDemoSeeder::class);
    }
}
