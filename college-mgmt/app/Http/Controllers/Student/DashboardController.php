<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\{Semester, Notice, TimetableEntry, TimetableSlot};
use App\Services\TimetableService;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('login');

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','students'))->latest()->take(5)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $grid = $currentSemester ? $this->service->buildWeeklyGrid($currentSemester->id, $student->course_id) : [];

        $todayDay = now()->dayOfWeekIso; // 1=Mon...6=Sat
        $todayClasses = [];
        if (isset($grid[$todayDay])) {
            $todayClasses = array_filter($grid[$todayDay]);
        }

        return view('student.dashboard', compact('student','currentSemester','notices','slots','grid','todayClasses'));
    }
}
