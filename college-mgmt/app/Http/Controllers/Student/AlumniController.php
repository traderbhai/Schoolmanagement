<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{AlumniProfile, Student};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlumniController extends Controller {
    public function index(Request $request) {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $query = AlumniProfile::with(['student.user', 'student.program'])
            ->where('is_verified', true);

        // Filter to same program by default
        if ($request->boolean('all_programs', false) === false) {
            $query->whereHas('student', fn($q) =>
                $q->where('program_id', $student->program_id)
            );
        }

        if ($request->filled('year')) {
            $query->where('graduation_year', $request->year);
        }

        if ($request->filled('company')) {
            $query->where('current_employer', 'like', '%' . $request->company . '%');
        }

        $alumni = $query->orderByDesc('graduation_year')->paginate(24)->withQueryString();

        $years = AlumniProfile::where('is_verified', true)
            ->selectRaw('DISTINCT graduation_year')
            ->orderByDesc('graduation_year')
            ->pluck('graduation_year');

        $sameProgramCount = AlumniProfile::where('is_verified', true)
            ->whereHas('student', fn($q) => $q->where('program_id', $student->program_id))
            ->count();
        $allVerifiedCount = AlumniProfile::where('is_verified', true)->count();
        $alumniPriority = $this->alumniPriority($sameProgramCount, $allVerifiedCount, $request->boolean('all_programs', false));

        return view('student.alumni.index', compact('alumni', 'years', 'student', 'sameProgramCount', 'allVerifiedCount', 'alumniPriority'));
    }

    private function alumniPriority(int $sameProgramCount, int $allVerifiedCount, bool $showingAllPrograms): array
    {
        if ($sameProgramCount > 0 && ! $showingAllPrograms) {
            return [
                'level' => 'info',
                'title' => "{$sameProgramCount} alumni from your program",
                'body' => 'Start with alumni from your academic program for relevant mentoring, referrals, and career paths.',
            ];
        }

        if ($allVerifiedCount > 0) {
            return [
                'level' => 'info',
                'title' => "{$allVerifiedCount} verified alumni available",
                'body' => 'Use filters to find alumni by graduation year, employer, or all programs.',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'Alumni network is being built',
            'body' => 'Verified alumni profiles will appear here after CMC confirms graduate career details.',
        ];
    }
}
