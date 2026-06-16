<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use Illuminate\Support\Facades\Auth;

class SubjectAnnouncementController extends Controller
{
    private function ensureEnrolled($student, Subject $subject): void
    {
        $canonical = $student->subjectEnrollments()
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->exists();

        $legacy = Enrollment::where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->whereIn('status', ['active', 'enrolled'])
            ->exists();

        abort_unless($canonical || $legacy, 403, 'You are not enrolled in this subject.');
    }

    public function index(Subject $subject)
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $this->ensureEnrolled($student, $subject);

        $announcements = SubjectAnnouncement::where('subject_id', $subject->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->with('poster')
            ->paginate(20);

        return view('student.announcements.index', compact('subject', 'announcements'));
    }
}
