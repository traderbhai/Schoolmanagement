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

        return view('student.alumni.index', compact('alumni', 'years', 'student'));
    }
}
