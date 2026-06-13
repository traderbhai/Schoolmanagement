<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\BookIssue;
use App\Models\LibraryReservation;
use Illuminate\Http\Request;

class LibraryController extends Controller {
    public function index() {
        $student = auth()->user()->student;
        $currentIssues = BookIssue::where('student_id', $student->id)
            ->whereIn('status', ['issued','overdue'])
            ->with('bookCopy.book')
            ->get();
        $unpaidFines = BookIssue::where('student_id', $student->id)
            ->where('fine_paid', false)
            ->where('fine_amount', '>', 0)
            ->with('bookCopy.book')
            ->get();
        $reservations = LibraryReservation::where('student_id', $student->id)
            ->with('book')
            ->latest('reserved_at')
            ->take(10)
            ->get();
        $totalFine = $unpaidFines->sum('fine_amount');
        return view('student.library.index', compact('currentIssues','unpaidFines','totalFine','reservations'));
    }
}
