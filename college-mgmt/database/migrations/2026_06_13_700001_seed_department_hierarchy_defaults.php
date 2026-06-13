<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $operationalDepartments = [
            ['code' => 'ACAD', 'name' => 'Academic Operations', 'head_name' => 'Academic Head', 'description' => 'Academic planning, curriculum, timetable, approvals, and academic monitoring.'],
            ['code' => 'ACC', 'name' => 'Accounts', 'head_name' => 'Accounts Head', 'description' => 'Fee collection, dues, reconciliation, scholarships, and finance reporting.'],
            ['code' => 'EXAM', 'name' => 'Exam Cell', 'head_name' => 'Exam Cell Head', 'description' => 'Exam scheduling, hall tickets, marks, results, appeals, and publishing.'],
            ['code' => 'CMC', 'name' => 'Career Management Cell', 'head_name' => 'CMC Head', 'description' => 'Placements, companies, drives, internships, alumni, and career reporting.'],
            ['code' => 'HOSTEL', 'name' => 'Hostel', 'head_name' => 'Hostel Warden', 'description' => 'Hostel allocation, complaints, outpasses, rooms, and hostel fees.'],
            ['code' => 'TRANSPORT', 'name' => 'Transport', 'head_name' => 'Transport Head', 'description' => 'Routes, stops, vehicles, drivers, and student transport assignments.'],
            ['code' => 'LIB', 'name' => 'Library', 'head_name' => 'Librarian', 'description' => 'Catalog, circulation, memberships, issue/return workflows, and fines.'],
        ];

        foreach ($operationalDepartments as $department) {
            DB::table('departments')->updateOrInsert(
                ['code' => $department['code']],
                $department + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $defaultRoles = [
            ['Department Owner', 'department_owner', 10, true, true, true, ['manage_department_settings', 'configure_department', 'view_all', 'assign_work', 'export_reports']],
            ['Department Manager', 'department_manager', 30, true, true, true, ['view_team', 'assign_work', 'export_reports']],
            ['Department Supervisor', 'department_supervisor', 50, true, true, true, ['view_team', 'assign_work']],
            ['Department Staff', 'department_staff', 80, false, false, false, ['view_assigned', 'operate']],
            ['Department Viewer', 'department_viewer', 90, false, true, false, ['view_assigned']],
        ];

        $departments = DB::table('departments')->where('is_active', true)->get(['id']);

        foreach ($departments as $department) {
            foreach ($defaultRoles as [$name, $code, $level, $manage, $viewTeam, $assign, $permissions]) {
                DB::table('department_roles')->updateOrInsert(
                    ['department_id' => $department->id, 'code' => $code],
                    [
                        'name' => $name,
                        'level' => $level,
                        'can_manage_lower_levels' => $manage,
                        'can_view_team_data' => $viewTeam,
                        'can_assign_work' => $assign,
                        'permissions' => json_encode($permissions),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        DB::table('department_roles')
            ->whereIn('code', ['admission_director', 'admission_head'])
            ->update([
                'permissions' => json_encode(['manage_department_settings', 'configure_department', 'view_all', 'assign_work', 'approve_offers', 'configure_process', 'export_reports']),
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('department_roles')->whereIn('code', [
            'department_owner',
            'department_manager',
            'department_supervisor',
            'department_staff',
            'department_viewer',
        ])->delete();
    }
};
