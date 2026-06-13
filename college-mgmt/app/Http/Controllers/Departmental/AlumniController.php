<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{AlumniProfile, Student};
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    public function index(Request $request)
    {
        $query = AlumniProfile::with(['student.user', 'student.program'])->latest();
        if ($request->filled('graduation_year')) $query->where('graduation_year', $request->graduation_year);
        if ($request->filled('verified')) $query->where('is_verified', $request->verified === '1');
        $alumni = $query->paginate(20)->withQueryString();
        $years  = AlumniProfile::distinct()->pluck('graduation_year')->sortDesc();

        $totalAlumni = AlumniProfile::count();
        $verifiedCount = AlumniProfile::where('is_verified', true)->count();
        $unverifiedCount = AlumniProfile::where('is_verified', false)->count();
        $recentGraduatesMissingProfiles = Student::where('status', 'graduated')
            ->whereDoesntHave('alumniProfile')
            ->count();

        $alumniPriority = $this->alumniPriority($unverifiedCount, $recentGraduatesMissingProfiles, $totalAlumni);

        return view('departmental.alumni.index', compact(
            'alumni', 'years', 'totalAlumni', 'verifiedCount', 'unverifiedCount', 'recentGraduatesMissingProfiles', 'alumniPriority'
        ));
    }

    public function create()
    {
        $students = Student::with('user')->whereIn('status', ['graduated', 'active'])->get();
        return view('departmental.alumni.create', compact('students'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'graduation_year'  => 'required|integer|min:2000|max:' . now()->year,
            'current_employer' => 'nullable|string|max:255',
            'current_role'     => 'nullable|string|max:255',
            'current_salary'   => 'nullable|numeric|min:0',
            'city'             => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',
            'linkedin_url'     => 'nullable|url|max:255',
            'feedback'         => 'nullable|string',
        ]);
        $v['country'] = $v['country'] ?? 'India';
        AlumniProfile::updateOrCreate(['student_id' => $v['student_id']], $v);
        return redirect()->route('cmc.alumni.index')->with('success', 'Alumni profile saved.');
    }

    public function verify(AlumniProfile $alumniProfile)
    {
        if ($alumniProfile->is_verified) {
            return back()->with('success', 'Alumni profile is already verified.');
        }

        $alumniProfile->update(['is_verified' => true]);
        return back()->with('success', 'Alumni profile verified.');
    }

    private function alumniPriority(int $unverifiedCount, int $missingProfiles, int $totalAlumni): array
    {
        if ($unverifiedCount > 0) {
            return [
                'level' => 'warning',
                'title' => "Verify {$unverifiedCount} alumni profile" . ($unverifiedCount === 1 ? '' : 's'),
                'body' => 'Unverified alumni are hidden from the student network. Verify accurate profiles after checking career details.',
                'route' => route('cmc.alumni.index', ['verified' => '0']),
                'action' => 'Review Unverified',
            ];
        }

        if ($missingProfiles > 0) {
            return [
                'level' => 'info',
                'title' => "Create {$missingProfiles} graduate alumni profile" . ($missingProfiles === 1 ? '' : 's'),
                'body' => 'Graduated students without alumni records reduce network coverage for mentoring and referrals.',
                'route' => route('cmc.alumni.create'),
                'action' => 'Add Alumni',
            ];
        }

        if ($totalAlumni === 0) {
            return [
                'level' => 'warning',
                'title' => 'Build the alumni network',
                'body' => 'No alumni profiles are recorded yet. Add verified alumni to make the student network useful.',
                'route' => route('cmc.alumni.create'),
                'action' => 'Add Alumni',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'Alumni network is current',
            'body' => 'Use filters to review alumni by year, program, employer, and verification status.',
            'route' => route('cmc.alumni.index'),
            'action' => 'Review Alumni',
        ];
    }
}
