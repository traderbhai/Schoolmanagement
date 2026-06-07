<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Student, Internship};
use Illuminate\Support\Facades\Auth;

class InternshipViewController extends Controller {
    public function index() {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $internships = Internship::where('student_id', $student->id)
            ->with('company')
            ->latest('start_date')
            ->get();
        return view('student.internships.index', compact('internships'));
    }
}
