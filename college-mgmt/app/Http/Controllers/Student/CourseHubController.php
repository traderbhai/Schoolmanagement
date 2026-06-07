<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\StudyMaterial;
use App\Models\SubjectAnnouncement;
use App\Models\Assignment;
use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

class CourseHubController extends Controller
{
    private function getStudent()
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        return $student;
    }

    public function index()
    {
        $student = $this->getStudent();
        $subjects = $student->subjects()->with('teachers')->get();

        return view('student.courses.index', compact('subjects'));
    }

    public function show(Subject $subject)
    {
        $student = $this->getStudent();

        $materials = StudyMaterial::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('type');

        $announcements = SubjectAnnouncement::where('subject_id', $subject->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit(5)
            ->with('poster')
            ->get();

        $assignments = Assignment::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->with(['submissions' => fn($q) => $q->where('student_id', $student->id)])
            ->orderBy('due_at')
            ->get();

        $quizzes = Quiz::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->with(['attempts' => fn($q) => $q->where('student_id', $student->id)])
            ->get();

        return view('student.courses.show', compact('subject', 'materials', 'announcements', 'assignments', 'quizzes'));
    }
}
