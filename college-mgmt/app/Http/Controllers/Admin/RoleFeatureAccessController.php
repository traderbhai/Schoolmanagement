<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleFeatureAccess;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class RoleFeatureAccessController extends Controller
{
    // Define all features used in the system
    private const FEATURES = [
        'exam' => [
            'exam.enter_marks' => 'Enter exam marks',
            'exam.publish_results' => 'Publish exam results',
            'exam.create_admit_card' => 'Create admit cards',
        ],
        'admission' => [
            'admission.approve_offers' => 'Approve offer letters',
            'admission.verify_documents' => 'Verify applicant documents',
            'admission.manage_leads' => 'Manage leads and enquiries',
        ],
        'approval' => [
            'approval.dean_sign_off' => 'Dean approval sign-off',
            'approval.chair_sign_off' => 'Program chair approval',
            'approval.hod_approve_leave' => 'HOD approve leave requests',
        ],
        'program' => [
            'program.edit_curriculum' => 'Edit program curriculum',
            'program.manage_subjects' => 'Manage subjects',
            'program.view_analytics' => 'View program analytics',
        ],
        'faculty' => [
            'faculty.mark_attendance' => 'Mark student attendance',
            'faculty.manage_assignments' => 'Manage assignments',
            'faculty.view_own_courses' => 'View own courses',
        ],
        'placement' => [
            'placement.create_drive' => 'Create placement drives',
            'placement.manage_internships' => 'Manage internships',
            'placement.view_placements' => 'View placement statistics',
        ],
        'finance' => [
            'finance.create_fee_demand' => 'Create fee demands',
            'finance.verify_payments' => 'Verify fee payments',
            'finance.reconcile_accounts' => 'Reconcile accounts',
        ],
    ];

    public function index()
    {
        $roles = Role::orderBy('name')->get();
        $features = self::FEATURES;

        $accessMatrix = [];
        foreach ($roles as $role) {
            $accessMatrix[$role->id] = [];
            foreach (array_keys(collect($features)->flatten(1)) as $featureCode) {
                $access = RoleFeatureAccess::where('role_id', $role->id)
                    ->where('feature_code', $featureCode)
                    ->first();
                $accessMatrix[$role->id][$featureCode] = $access?->access_level ?? 'none';
            }
        }

        return view('admin.roles.feature-access.index', compact('roles', 'features', 'accessMatrix'));
    }

    public function edit(Role $role)
    {
        $features = self::FEATURES;
        $roleAccess = RoleFeatureAccess::where('role_id', $role->id)
            ->get()
            ->keyBy('feature_code');

        return view('admin.roles.feature-access.edit', compact('role', 'features', 'roleAccess'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'access_matrix' => 'array',
            'access_matrix.*' => 'in:none,view,create,edit,approve,delete',
        ]);

        $matrix = $validated['access_matrix'] ?? [];

        // Remove all existing access for this role
        RoleFeatureAccess::where('role_id', $role->id)->delete();

        // Add new access
        foreach ($matrix as $featureCode => $accessLevel) {
            if ($accessLevel !== 'none') {
                RoleFeatureAccess::create([
                    'role_id' => $role->id,
                    'feature_code' => $featureCode,
                    'access_level' => $accessLevel,
                ]);
            }
        }

        AuditLog::logPermissionChanged(auth()->user(), $role, [
            'feature_access_updated' => true,
            'features_count' => count(array_filter($matrix, fn($v) => $v !== 'none')),
        ]);

        return redirect('admin/roles/feature-access')
            ->with('success', 'Feature access updated for ' . $role->name);
    }

    public static function getFeatures()
    {
        return self::FEATURES;
    }
}
