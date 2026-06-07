<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectAnnouncement;
use Illuminate\Support\Facades\Auth;

class SubjectAnnouncementController extends Controller
{
    public function index(Subject $subject)
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $announcements = SubjectAnnouncement::where('subject_id', $subject->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->with('poster')
            ->paginate(20);

        return view('student.announcements.index', compact('subject', 'announcements'));
    }
}
