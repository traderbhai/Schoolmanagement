<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_scope_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_member_id')->nullable()->constrained('department_members')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type')->index();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_code')->nullable();
            $table->string('scope_name');
            $table->string('context')->nullable()->index();
            $table->boolean('can_manage')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_code']);
        });

        $now = now();

        $acadDepartmentId = DB::table('departments')->where('code', 'ACAD')->value('id');
        if (!$acadDepartmentId) {
            $acadDepartmentId = DB::table('departments')->insertGetId([
                'name' => 'Academic Operations',
                'code' => 'ACAD',
                'description' => 'Academic planning, PMC, CoE, IQAC, program leadership, and student academic operations.',
                'head_name' => 'Dean Academics',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = [
            ['Department Owner', 'academic_department_owner', 5, true, true, true, ['manage_department_settings', 'configure_department', 'view_all', 'assign_work', 'manage_academic_scopes', 'export_reports']],
            ['Dean Academics', 'dean_academics', 10, true, true, true, ['manage_department_settings', 'configure_department', 'view_all', 'assign_work', 'manage_academic_scopes', 'approve_academic_decisions', 'export_reports']],
            ['PMC Head', 'pmc_head', 20, true, true, true, ['view_team', 'assign_work', 'manage_curriculum', 'manage_timetable', 'manage_academic_scopes', 'export_reports']],
            ['PMC Manager', 'pmc_manager', 35, true, true, true, ['view_team', 'assign_work', 'manage_curriculum', 'manage_timetable']],
            ['PMC Officer', 'pmc_officer', 60, false, false, false, ['view_assigned', 'operate_curriculum', 'operate_timetable']],
            ['CoE', 'coe', 20, true, true, true, ['view_team', 'assign_work', 'manage_examinations', 'publish_results', 'manage_academic_scopes', 'export_reports']],
            ['Exam Manager', 'exam_manager', 35, true, true, true, ['view_team', 'assign_work', 'manage_examinations']],
            ['Exam Officer', 'exam_officer', 60, false, false, false, ['view_assigned', 'operate_examinations']],
            ['IQAC Head', 'iqac_head', 20, true, true, true, ['view_team', 'assign_work', 'manage_quality_audits', 'manage_academic_scopes', 'export_reports']],
            ['IQAC Manager', 'iqac_manager', 35, true, true, true, ['view_team', 'assign_work', 'manage_quality_audits']],
            ['IQAC Officer', 'iqac_officer', 60, false, false, false, ['view_assigned', 'operate_quality_audits']],
            ['Program Director', 'program_director', 25, true, true, true, ['view_team', 'assign_work', 'manage_program', 'approve_academic_decisions', 'export_reports']],
            ['Program Leader', 'program_leader', 40, true, true, true, ['view_team', 'assign_work', 'manage_program']],
            ['Year/Semester Coordinator', 'semester_coordinator', 55, true, true, true, ['view_team', 'assign_work', 'manage_term']],
            ['Course Coordinator', 'course_coordinator', 70, false, true, false, ['view_assigned', 'manage_course']],
            ['Faculty Mentor', 'faculty_mentor', 80, false, false, false, ['view_assigned', 'mentor_students']],
        ];

        foreach ($roles as [$name, $code, $level, $manage, $viewTeam, $assign, $permissions]) {
            DB::table('department_roles')->updateOrInsert(
                ['department_id' => $acadDepartmentId, 'code' => $code],
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

    public function down(): void
    {
        Schema::dropIfExists('academic_scope_assignments');
    }
};
