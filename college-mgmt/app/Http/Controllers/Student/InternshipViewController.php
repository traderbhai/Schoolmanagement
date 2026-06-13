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

        $internshipPriority = $this->internshipPriority($internships);

        return view('student.internships.index', compact('internships', 'internshipPriority'));
    }

    private function internshipPriority($internships): array
    {
        $ongoing = $internships->firstWhere('status', 'ongoing');
        if ($ongoing) {
            return [
                'level' => 'info',
                'title' => 'Internship currently in progress',
                'body' => 'Keep supervisor details and responsibilities visible, and coordinate completion feedback with CMC when the internship ends.',
            ];
        }

        $completed = $internships->firstWhere('status', 'completed');
        if ($completed) {
            return [
                'level' => 'success',
                'title' => 'Internship record completed',
                'body' => 'Review completion feedback, rating, and duration for your resume and academic records.',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No internship record yet',
            'body' => 'Internships, industrial training, and live projects are added by the placement cell after confirmation.',
        ];
    }
}
