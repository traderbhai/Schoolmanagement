<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use App\Models\RoleFeatureAccess;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class RoleFeatureAccessController extends Controller
{
    public static function getFeatures(): array
    {
        return [
            ['code' => 'exam.enter_marks',       'label' => 'Enter exam marks',         'category' => 'Exam'],
            ['code' => 'exam.publish_results',    'label' => 'Publish exam results',     'category' => 'Exam'],
            ['code' => 'exam.create_admit_card',  'label' => 'Create admit cards',       'category' => 'Exam'],
            ['code' => 'admission.approve_offers',    'label' => 'Approve offer letters',     'category' => 'Admission'],
            ['code' => 'admission.verify_documents',  'label' => 'Verify applicant documents','category' => 'Admission'],
            ['code' => 'admission.manage_leads',      'label' => 'Manage leads & enquiries',  'category' => 'Admission'],
            ['code' => 'approval.dean_sign_off',      'label' => 'Dean approval sign-off',    'category' => 'Approvals'],
            ['code' => 'approval.chair_sign_off',     'label' => 'Program chair approval',    'category' => 'Approvals'],
            ['code' => 'approval.hod_approve_leave',  'label' => 'HOD approve leave',         'category' => 'Approvals'],
            ['code' => 'program.edit_curriculum',     'label' => 'Edit program curriculum',   'category' => 'Program'],
            ['code' => 'program.manage_subjects',     'label' => 'Manage subjects',            'category' => 'Program'],
            ['code' => 'program.view_analytics',      'label' => 'View program analytics',    'category' => 'Program'],
            ['code' => 'faculty.mark_attendance',     'label' => 'Mark student attendance',   'category' => 'Faculty'],
            ['code' => 'faculty.manage_assignments',  'label' => 'Manage assignments',         'category' => 'Faculty'],
            ['code' => 'faculty.view_own_courses',    'label' => 'View own courses',           'category' => 'Faculty'],
            ['code' => 'placement.create_drive',      'label' => 'Create placement drives',   'category' => 'Placement'],
            ['code' => 'placement.manage_internships','label' => 'Manage internships',         'category' => 'Placement'],
            ['code' => 'placement.view_placements',   'label' => 'View placement statistics', 'category' => 'Placement'],
            ['code' => 'finance.create_fee_demand',   'label' => 'Create fee demands',         'category' => 'Finance'],
            ['code' => 'finance.verify_payments',     'label' => 'Verify fee payments',        'category' => 'Finance'],
            ['code' => 'finance.reconcile_accounts',  'label' => 'Reconcile accounts',         'category' => 'Finance'],
        ];
    }

    public function index()
    {
        $this->authorizeRolePermissionManagement();

        $roles = Role::orderBy('name')->get();
        $features = self::getFeatures();

        // Build access matrix: [role_id][feature_code] => access_level
        $accessMatrix = [];
        $allAccess = RoleFeatureAccess::whereIn('role_id', $roles->pluck('id'))->get();

        foreach ($roles as $role) {
            $accessMatrix[$role->id] = [];
        }
        foreach ($allAccess as $record) {
            $accessMatrix[$record->role_id][$record->feature_code] = $record->access_level;
        }

        return view('admin.roles.feature-access.index', compact('roles', 'features', 'accessMatrix'));
    }

    public function edit(Role $role)
    {
        $this->authorizeRolePermissionManagement();

        $features = self::getFeatures();
        $currentAccess = RoleFeatureAccess::where('role_id', $role->id)
            ->get()
            ->pluck('access_level', 'feature_code')
            ->toArray();

        return view('admin.roles.feature-access.edit', compact('role', 'features', 'currentAccess'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeRolePermissionManagement();

        $validated = $request->validate([
            'access'   => 'array',
            'access.*' => 'in:,view,create,edit,approve,delete',
        ]);

        $matrix = $validated['access'] ?? [];

        RoleFeatureAccess::where('role_id', $role->id)->delete();

        foreach ($matrix as $featureCode => $accessLevel) {
            if ($accessLevel !== '') {
                RoleFeatureAccess::create([
                    'role_id'      => $role->id,
                    'feature_code' => $featureCode,
                    'access_level' => $accessLevel,
                ]);
            }
        }

        AuditLog::logPermissionChanged(auth()->user(), $role, [
            'summary'         => 'Feature access updated',
            'features_active' => count(array_filter($matrix, fn($v) => $v !== '')),
        ]);

        return redirect()->route('admin.roles.feature-access.index')
            ->with('success', 'Feature access updated for ' . $role->name);
    }

    private function authorizeRolePermissionManagement(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageRoleAssignments(auth()->user()), 403);
    }
}
