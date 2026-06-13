<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\BookIssue;
use App\Models\Student;

class LibraryController extends Controller
{
    public function index()
    {
        $student = Student::where('user_id', auth()->id())->firstOrFail();
        $currentIssues = BookIssue::where('student_id', $student->id)
            ->whereIn('status', ['issued','overdue'])
            ->with('bookCopy.book')
            ->get();
        $fines = BookIssue::where('student_id', $student->id)
            ->where('fine_paid', false)
            ->where('fine_amount', '>', 0)
            ->with('bookCopy.book')
            ->get();
        $history = BookIssue::where('student_id', $student->id)
            ->where('status', 'returned')
            ->with('bookCopy.book')
            ->latest()
            ->limit(10)
            ->get();
        return view('student.library.index', compact('currentIssues', 'fines', 'history'));
    }
}
