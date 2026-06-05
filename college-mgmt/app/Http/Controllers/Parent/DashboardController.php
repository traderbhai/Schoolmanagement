<?php
namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\{ParentProfile, Student, Notice, Attendance, ExamResult, FeeStructure, FeePayment, Semester};
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function getParent(): ParentProfile
    {
        return ParentProfile::where('user_id', Auth::id())
            ->with('students.user', 'students.course')
            ->firstOrFail();
    }

    public function index()
    {
        $parent = $this->getParent();
        $children = $parent->students;

        $childrenData = $children->map(function ($student) {
            $totalAtt   = Attendance::where('student_id', $student->id)->count();
            $presentAtt = Attendance::where('student_id', $student->id)->whereIn('status', ['present', 'late'])->count();
            $attendancePct = $totalAtt > 0 ? round(($presentAtt / $totalAtt) * 100) : null;

            $currentSemester = Semester::current();
            $sgpa = null;
            if ($currentSemester) {
                $results = ExamResult::with('exam')
                    ->whereHas('exam', fn($q) => $q->where('semester_id', $currentSemester->id))
                    ->where('student_id', $student->id)
                    ->where('is_absent', false)
                    ->get();
                if ($results->count() > 0) {
                    $totalGp = $results->sum(fn($r) => $r->exam->total_marks > 0
                        ? ($r->marks_obtained / $r->exam->total_marks) * 10 : 0);
                    $sgpa = round($totalGp / $results->count(), 2);
                }
            }

            $feeDue  = $student->course_id ? FeeStructure::where('course_id', $student->course_id)->sum('amount') : 0;
            $feePaid = FeePayment::where('student_id', $student->id)->where('status', 'paid')->sum('amount_paid');
            $balance = max(0, $feeDue - $feePaid);

            return [
                'student'       => $student,
                'attendancePct' => $attendancePct,
                'sgpa'          => $sgpa,
                'balance'       => $balance,
            ];
        });

        $notices = Notice::where('is_published', true)
            ->where('publish_date', '<=', now())
            ->latest()->take(5)->get();

        return view('parent.dashboard', compact('parent', 'children', 'childrenData', 'notices'));
    }

    public function children()
    {
        $parent = $this->getParent();
        $children = $parent->students()->with('user', 'course', 'department')->get();
        return view('parent.children', compact('parent', 'children'));
    }

    public function attendance(Student $student)
    {
        $parent = $this->getParent();
        abort_unless($parent->students->contains($student), 403);

        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $currentSemester = Semester::current() ?? $semesters->first();
        $semesterId = request('semester_id') ?? optional($currentSemester)->id;

        $report = [];
        if ($semesterId) {
            $attendances = Attendance::with(['timetableEntry.subject', 'timetableEntry.slot'])
                ->where('student_id', $student->id)
                ->whereHas('timetableEntry', fn($q) => $q->where('semester_id', $semesterId))
                ->get();

            $grouped = $attendances->groupBy(fn($a) => $a->timetableEntry->subject->name ?? 'Unknown');
            foreach ($grouped as $subjectName => $records) {
                $total   = $records->count();
                $present = $records->whereIn('status', ['present', 'late'])->count();
                $absent  = $records->where('status', 'absent')->count();
                $late    = $records->where('status', 'late')->count();
                $pct     = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                $report[] = [
                    'subject' => $subjectName,
                    'total'   => $total,
                    'present' => $present,
                    'absent'  => $absent,
                    'late'    => $late,
                    'pct'     => $pct,
                    'low'     => $pct < 75,
                ];
            }
        }

        return view('parent.attendance', compact('parent', 'student', 'semesters', 'semesterId', 'report', 'currentSemester'));
    }

    public function results(Student $student)
    {
        $parent = $this->getParent();
        abort_unless($parent->students->contains($student), 403);

        $semesters = Semester::with('academicYear')->orderByDesc('id')->get();
        $currentSemester = Semester::current() ?? $semesters->first();
        $semesterId = request('semester_id') ?? optional($currentSemester)->id;

        $results = [];
        if ($semesterId) {
            $results = ExamResult::with('exam.subject')
                ->whereHas('exam', fn($q) => $q->where('semester_id', $semesterId))
                ->where('student_id', $student->id)
                ->get();
        }

        return view('parent.results', compact('parent', 'student', 'semesters', 'semesterId', 'results', 'currentSemester'));
    }

    public function fees(Student $student)
    {
        $parent = $this->getParent();
        abort_unless($parent->students->contains($student), 403);

        $payments = $student->feePayments()->with('feeStructure')->latest()->get();
        $feeDue   = $student->course_id ? FeeStructure::where('course_id', $student->course_id)->sum('amount') : 0;
        $feePaid  = $student->feePayments()->where('status', 'paid')->sum('amount_paid');
        $balance  = max(0, $feeDue - $feePaid);

        return view('parent.fees', compact('parent', 'student', 'payments', 'feeDue', 'feePaid', 'balance'));
    }

    public function notices()
    {
        $parent = $this->getParent();
        $notices = Notice::where('is_published', true)
            ->where('publish_date', '<=', now())
            ->where(fn($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()))
            ->latest()
            ->paginate(10);

        return view('parent.notices', compact('parent', 'notices'));
    }
}
