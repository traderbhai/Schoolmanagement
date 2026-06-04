<?php
namespace App\Http\Controllers\Teacher;
use App\Http\Controllers\Controller;
use App\Models\{Semester, Notice, TimetableSlot};
use App\Services\TimetableService;

class DashboardController extends Controller
{
    public function __construct(private TimetableService $service) {}

    public function index() {
        $user = auth()->user()->load('teacher.department');
        $teacher = $user->teacher;
        if (!$teacher) return redirect()->route('login');

        $currentSemester = Semester::current();
        $notices = Notice::active()->where(fn($q) => $q->where('audience','all')->orWhere('audience','teachers'))->latest()->take(5)->get();
        $slots = TimetableSlot::where('is_active',true)->orderBy('sort_order')->get();
        $grid = $currentSemester ? $this->service->buildWeeklyGrid($currentSemester->id, null, $teacher->id) : [];
        $weeklyLoad = $currentSemester ? $this->service->getTeacherWeeklyLoad($teacher->id, $currentSemester->id) : 0;

        $todayDay = now()->dayOfWeekIso;
        $todayClasses = isset($grid[$todayDay]) ? array_filter($grid[$todayDay]) : [];

        return view('teacher.dashboard', compact('teacher','currentSemester','notices','slots','grid','todayClasses','weeklyLoad'));
    }
}
