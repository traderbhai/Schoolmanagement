<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Placement;
use App\Models\PlacementDrive;
use App\Models\Student;
use Illuminate\Database\Seeder;

class DemoPlacementSeeder extends Seeder
{
    public function run(): void
    {
        $company1 = Company::firstOrCreate(['name' => 'InfoSys Ltd'], [
            'industry' => 'IT / Consulting',
            'contact_person' => 'Ms. Ritu Sharma',
            'contact_email' => 'campus@infosys.com',
            'is_active' => true,
        ]);
        $company2 = Company::firstOrCreate(['name' => 'HDFC Bank'], [
            'industry' => 'Banking & Finance',
            'contact_person' => 'Mr. Vijay Menon',
            'contact_email' => 'campus@hdfc.com',
            'is_active' => true,
        ]);
        $drive1 = PlacementDrive::firstOrCreate(['title' => 'InfoSys Campus Drive 2025'], [
            'company_id' => $company1->id,
            'job_role' => 'Management Trainee',
            'package' => '6.5 LPA',
            'min_cgpa' => 6.0,
            'drive_date' => '2025-02-15',
            'last_apply_date' => '2025-02-10',
            'location' => 'Bangalore',
            'status' => 'upcoming',
            'vacancies' => 10,
        ]);
        PlacementDrive::firstOrCreate(['title' => 'HDFC Bank PGDM Drive'], [
            'company_id' => $company2->id,
            'job_role' => 'Relationship Manager',
            'package' => '7 LPA',
            'min_cgpa' => 6.5,
            'drive_date' => '2025-03-01',
            'last_apply_date' => '2025-02-25',
            'location' => 'Mumbai',
            'status' => 'upcoming',
            'vacancies' => 5,
        ]);

        Student::query()
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->values()
            ->each(function (Student $student, int $index) use ($drive1) {
                Placement::firstOrCreate(['drive_id' => $drive1->id, 'student_id' => $student->id], [
                    'application_status' => ['applied', 'shortlisted', 'applied'][$index],
                ]);
            });
    }
}
