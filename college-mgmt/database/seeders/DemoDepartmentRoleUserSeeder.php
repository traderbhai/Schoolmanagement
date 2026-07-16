<?php

namespace Database\Seeders;

use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\RoleProgramAssignment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDepartmentRoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@demo.edu')->first();
        $program = Program::where('code', 'PGDM')->first();

        $deanUser = $this->user('dean@college.com', 'Dr. Meena Iyer', 'dean_academics');
        $chairUser = $this->user('chair@college.com', 'Prof. Anil Gupta', 'program_chair');
        $this->user('exam@college.com', 'Ritu Verma', 'exam_cell');
        $hodUser = $this->user('hod@college.com', 'Dr. Suresh Nair', 'hod');
        $this->user('accounts@college.com', 'Pradeep Sharma', 'accounts_officer');
        $this->user('cmc@college.com', 'CMC Officer', 'cmc');
        $this->user('director@college.com', 'Institute Director', 'director');

        if ($program) {
            $assignById = $admin?->id ?? $deanUser->id;
            RoleProgramAssignment::firstOrCreate(
                ['user_id' => $chairUser->id, 'role_name' => 'program_chair', 'program_id' => $program->id],
                ['is_active' => true, 'assigned_by' => $assignById, 'assigned_at' => now()]
            );
            RoleProgramAssignment::firstOrCreate(
                ['user_id' => $hodUser->id, 'role_name' => 'hod', 'program_id' => $program->id],
                ['is_active' => true, 'assigned_by' => $assignById, 'assigned_at' => now()]
            );
        }

        $parentUser = $this->user('parent@demo.edu', 'Ramesh Kumar', 'parent');
        $parentProfile = ParentProfile::firstOrCreate(
            ['user_id' => $parentUser->id],
            ['relation' => 'father', 'phone' => '9876500000']
        );
        $parentChild = Student::whereHas('user', fn ($query) => $query->where('email', 'arjun.k@demo.edu'))->first();
        if ($parentChild && ! $parentProfile->students()->where('students.id', $parentChild->id)->exists()) {
            $parentProfile->students()->attach($parentChild->id);
        }
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $user->syncRoles([$role]);

        return $user;
    }
}
