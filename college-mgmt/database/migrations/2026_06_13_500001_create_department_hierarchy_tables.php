<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('level')->default(100)->index();
            $table->boolean('can_manage_lower_levels')->default(false);
            $table->boolean('can_view_team_data')->default(false);
            $table->boolean('can_assign_work')->default(false);
            $table->json('permissions')->nullable();
            $table->json('scope_rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['department_id', 'code']);
        });

        Schema::create('department_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('department_teams')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('custom')->index();
            $table->json('scope_rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['department_id', 'type']);
        });

        Schema::create('department_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reports_to_member_id')->nullable()->constrained('department_members')->nullOnDelete();
            $table->json('scope_rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['department_id', 'user_id', 'department_role_id'], 'department_member_role_unique');
            $table->index(['department_id', 'user_id']);
        });

        $admissionDepartmentId = DB::table('departments')->where('code', 'ADM')->value('id');
        if (!$admissionDepartmentId) {
            $admissionDepartmentId = DB::table('departments')->insertGetId([
                'name' => 'Admissions',
                'code' => 'ADM',
                'description' => 'Admission department operations and enrolment pipeline.',
                'head_name' => 'Admission Head',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $defaultRoles = [
            ['Admission Director', 'admission_director', 5, true, true, true, ['view_all', 'assign_work', 'approve_offers', 'configure_process', 'export_reports']],
            ['Admission Head', 'admission_head', 10, true, true, true, ['view_all', 'assign_work', 'approve_offers', 'configure_process', 'export_reports']],
            ['Admission Manager', 'admission_manager', 30, true, true, true, ['view_team', 'assign_work', 'export_reports']],
            ['Jr. Admission Manager', 'jr_admission_manager', 50, true, true, true, ['view_team', 'assign_work']],
            ['Admission Counsellor', 'admission_counsellor', 80, false, false, false, ['view_assigned', 'follow_up']],
            ['Telecaller', 'admission_telecaller', 90, false, false, false, ['view_assigned', 'follow_up']],
            ['Document Verifier', 'admission_document_verifier', 85, false, false, false, ['verify_documents']],
            ['Payment Coordinator', 'admission_payment_coordinator', 85, false, false, false, ['verify_payments']],
        ];

        foreach ($defaultRoles as [$name, $code, $level, $manage, $viewTeam, $assign, $permissions]) {
            DB::table('department_roles')->updateOrInsert(
                ['department_id' => $admissionDepartmentId, 'code' => $code],
                [
                    'name' => $name,
                    'level' => $level,
                    'can_manage_lower_levels' => $manage,
                    'can_view_team_data' => $viewTeam,
                    'can_assign_work' => $assign,
                    'permissions' => json_encode($permissions),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_members');
        Schema::dropIfExists('department_teams');
        Schema::dropIfExists('department_roles');
    }
};
