<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrgReportingLine;
use Illuminate\Http\Request;

class OrgHierarchyController extends Controller
{
    private array $allRoles = [
        'director'         => 'Director',
        'admin'            => 'Admin',
        'dean_academics'   => 'Dean (Academics)',
        'hod'              => 'Head of Department',
        'program_chair'    => 'Program Chair / PMC',
        'exam_cell'        => 'Exam Cell',
        'accounts_officer' => 'Accounts Officer',
        'cmc'              => 'CMC / Placement',
        'teacher'          => 'Teacher',
        'admission_director' => 'Admission Director',
        'admission_head'   => 'Admission Head',
        'admission_manager' => 'Admission Manager',
        'jr_admission_manager' => 'Jr. Admission Manager',
        'admission_counsellor' => 'Admission Counsellor',
        'admission_telecaller' => 'Admission Telecaller',
        'admission_officer'=> 'Admission Officer',
    ];

    public function index()
    {
        $this->authorizeSystemConfiguration();

        $lines = OrgReportingLine::where('is_active', true)
            ->orderBy('parent_role')
            ->orderBy('sort_order')
            ->get();
        $grouped = $lines->groupBy('parent_role');
        $allRoles = $this->allRoles;
        return view('admin.org-hierarchy.index', compact('lines', 'grouped', 'allRoles'));
    }

    public function store(Request $request)
    {
        $this->authorizeSystemConfiguration();

        $request->validate([
            'parent_role' => 'required|string|max:50',
            'child_role'  => 'required|string|max:50|different:parent_role',
        ]);
        $next = OrgReportingLine::where('parent_role', $request->parent_role)
            ->where('is_active', true)
            ->max('sort_order') + 1;
        OrgReportingLine::updateOrCreate(
            ['parent_role' => $request->parent_role, 'child_role' => $request->child_role],
            [
                'can_view_summary' => $request->boolean('can_view_summary', true),
                'can_view_full'    => $request->boolean('can_view_full'),
                'sort_order'       => $next,
                'is_active'        => true,
                'revoked_by'       => null,
                'revoked_at'       => null,
            ]
        );
        return back()->with('success', 'Reporting line added.');
    }

    public function update(Request $request, OrgReportingLine $line)
    {
        $this->authorizeSystemConfiguration();

        $line->update([
            'can_view_summary' => $request->boolean('can_view_summary'),
            'can_view_full'    => $request->boolean('can_view_full'),
        ]);
        return back()->with('success', 'Updated.');
    }

    public function destroy(OrgReportingLine $line)
    {
        $this->authorizeSystemConfiguration();

        $line->update([
            'is_active' => false,
            'revoked_by' => auth()->id(),
            'revoked_at' => now(),
        ]);

        AuditLog::log('org_reporting_line_revoked', $line, [
            'parent_role' => $line->parent_role,
            'child_role' => $line->child_role,
        ]);

        return back()->with('success', 'Reporting line removed.');
    }

    private function authorizeSystemConfiguration(): void
    {
        abort_unless(auth()->user() && AccessControl::canManageSystemConfiguration(auth()->user()), 403);
    }
}
