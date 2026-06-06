<?php

namespace Database\Seeders;

use App\Models\RoleFeatureAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleFeatureAccessSeeder extends Seeder
{
    public function run(): void
    {
        // [feature_code => [role_name => access_level]]
        $featureMatrix = [
            'exam.enter_marks' => [
                'exam_cell'      => 'edit',
                'dean_academics' => 'approve',
                'admin'          => 'delete',
            ],
            'exam.view_results' => [
                'student'          => 'view',
                'teacher'          => 'view',
                'faculty'          => 'view',
                'accounts_officer' => 'view',
                'exam_cell'        => 'view',
                'cmc'              => 'view',
                'program_chair'    => 'view',
                'hod'              => 'view',
                'admission_officer'=> 'view',
                'admission_head'   => 'view',
                'dean_academics'   => 'view',
                'admin'            => 'view',
            ],
            'exam.approve_results' => [
                'dean_academics' => 'approve',
                'admin'          => 'approve',
            ],
            'exam.schedule_exam' => [
                'exam_cell'      => 'create',
                'dean_academics' => 'approve',
                'admin'          => 'delete',
            ],
            'exam.manage_malpractice' => [
                'exam_cell'      => 'edit',
                'dean_academics' => 'approve',
                'admin'          => 'delete',
            ],
            'admission.view_applicants' => [
                'admission_head'   => 'view',
                'admission_officer'=> 'view',
                'dean_academics'   => 'view',
                'admin'            => 'view',
            ],
            'admission.approve_offers' => [
                'dean_academics' => 'approve',
                'admin'          => 'approve',
            ],
            'admission.process_docs' => [
                'admission_officer' => 'edit',
                'admission_head'    => 'approve',
            ],
            'admission.shortlist' => [
                'admission_head' => 'edit',
                'dean_academics' => 'approve',
            ],
            'enrollment.enroll_student' => [
                'admission_head' => 'create',
                'admin'          => 'create',
            ],
            'enrollment.view_enrolled' => [
                'student'          => 'view',
                'teacher'          => 'view',
                'faculty'          => 'view',
                'accounts_officer' => 'view',
                'exam_cell'        => 'view',
                'cmc'              => 'view',
                'program_chair'    => 'view',
                'hod'              => 'view',
                'admission_officer'=> 'view',
                'admission_head'   => 'view',
                'dean_academics'   => 'view',
                'admin'            => 'view',
            ],
            'approval.dean_sign_off' => [
                'dean_academics' => 'approve',
                'admin'          => 'approve',
            ],
            'approval.chair_sign_off' => [
                'program_chair'  => 'approve',
                'dean_academics' => 'approve',
                'admin'          => 'approve',
            ],
            'curriculum.view' => [
                'program_chair'  => 'view',
                'hod'            => 'view',
                'dean_academics' => 'view',
                'admin'          => 'view',
                'faculty'        => 'view',
                'teacher'        => 'view',
            ],
            'curriculum.edit' => [
                'program_chair'  => 'edit',
                'dean_academics' => 'approve',
                'admin'          => 'delete',
            ],
            'attendance.mark' => [
                'faculty'       => 'create',
                'teacher'       => 'create',
                'program_chair' => 'view',
                'admin'         => 'view',
            ],
            'attendance.view_report' => [
                'faculty'        => 'view',
                'teacher'        => 'view',
                'hod'            => 'view',
                'program_chair'  => 'view',
                'dean_academics' => 'view',
                'admin'          => 'view',
            ],
            'fee.view_demands' => [
                'accounts_officer' => 'view',
                'admin'            => 'view',
                'student'          => 'view',
            ],
            'fee.collect_payment' => [
                'accounts_officer' => 'create',
                'admin'            => 'create',
            ],
            'fee.reconcile' => [
                'accounts_officer' => 'edit',
                'admin'            => 'delete',
            ],
            'placement.view_drives' => [
                'cmc'     => 'view',
                'faculty' => 'view',
                'teacher' => 'view',
                'student' => 'view',
                'admin'   => 'view',
            ],
            'placement.create_drive' => [
                'cmc'   => 'create',
                'admin' => 'create',
            ],
            'placement.manage_drive' => [
                'cmc'   => 'edit',
                'admin' => 'delete',
            ],
            'report.view_institutional' => [
                'dean_academics' => 'view',
                'admin'          => 'view',
            ],
            'report.view_program' => [
                'program_chair'  => 'view',
                'hod'            => 'view',
                'dean_academics' => 'view',
                'admin'          => 'view',
            ],
            'user.manage_roles' => [
                'admin' => 'delete',
            ],
            'audit.view_log' => [
                'admin' => 'view',
            ],
        ];

        // Ensure all needed roles exist (idempotent)
        $allRoles = [];
        foreach ($featureMatrix as $roleAccess) {
            foreach (array_keys($roleAccess) as $roleName) {
                $allRoles[$roleName] = true;
            }
        }
        foreach (array_keys($allRoles) as $roleName) {
            Role::firstOrCreate(['name' => $roleName], ['guard_name' => 'web']);
        }

        foreach ($featureMatrix as $featureCode => $roleAccess) {
            foreach ($roleAccess as $roleName => $accessLevel) {
                $role = Role::where('name', $roleName)->first();
                if (!$role) {
                    continue; // skip if role not found
                }

                RoleFeatureAccess::updateOrCreate(
                    ['role_id' => $role->id, 'feature_code' => $featureCode],
                    ['access_level' => $accessLevel]
                );
            }
        }
    }
}
