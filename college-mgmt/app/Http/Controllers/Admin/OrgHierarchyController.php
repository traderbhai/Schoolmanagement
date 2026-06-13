<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $lines = OrgReportingLine::orderBy('parent_role')->orderBy('sort_order')->get();
        $grouped = $lines->groupBy('parent_role');
        $allRoles = $this->allRoles;
        return view('admin.org-hierarchy.index', compact('lines', 'grouped', 'allRoles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'parent_role' => 'required|string|max:50',
            'child_role'  => 'required|string|max:50|different:parent_role',
        ]);
        $next = OrgReportingLine::where('parent_role', $request->parent_role)->max('sort_order') + 1;
        OrgReportingLine::updateOrCreate(
            ['parent_role' => $request->parent_role, 'child_role' => $request->child_role],
            [
                'can_view_summary' => $request->boolean('can_view_summary', true),
                'can_view_full'    => $request->boolean('can_view_full'),
                'sort_order'       => $next,
            ]
        );
        return back()->with('success', 'Reporting line added.');
    }

    public function update(Request $request, OrgReportingLine $line)
    {
        $line->update([
            'can_view_summary' => $request->boolean('can_view_summary'),
            'can_view_full'    => $request->boolean('can_view_full'),
        ]);
        return back()->with('success', 'Updated.');
    }

    public function destroy(OrgReportingLine $line)
    {
        $line->delete();
        return back()->with('success', 'Reporting line removed.');
    }
}
