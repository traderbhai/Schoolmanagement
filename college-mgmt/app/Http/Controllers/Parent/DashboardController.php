<?php
namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\{ParentProfile, Student, Notice, Attendance, ExamResult, FeeDemand, FeePayment, Semester};
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    private function getParent(): ParentProfile
    {
        return ParentProfile::where('user_id', Auth::id())
            ->with('students.user', 'students.course', 'students.program', 'students.batch')
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

            $finance = $this->studentFinance($student);
            $priority = $this->studentPriority($attendancePct, $finance);
            $priority['route'] = match ($priority['type']) {
                'attendance' => route('parent.children.attendance', $student),
                'fees' => route('parent.children.fees', $student),
                default => route('parent.children'),
            };

            return [
                'student'       => $student,
                'attendancePct' => $attendancePct,
                'sgpa'          => $sgpa,
                'finance'       => $finance,
                'balance'       => $finance['balance'],
                'priority'      => $priority,
            ];
        });

        $notices = Notice::where('is_published', true)
            ->where('publish_date', '<=', now())
            ->latest()->take(5)->get();

        $parentPriority = $childrenData->first(fn($item) => $item['priority']['level'] !== 'none')['priority'] ?? [
            'level' => 'none',
            'title' => 'No urgent parent action today',
            'body' => 'Review notices, attendance, results, and fee updates when you have time.',
            'route' => route('parent.notices'),
            'action' => 'View Notices',
        ];

        return view('parent.dashboard', compact('parent', 'children', 'childrenData', 'notices', 'parentPriority'));
    }

    public function children()
    {
        $parent = $this->getParent();
        $children = $parent->students()->with('user', 'course', 'department', 'program', 'batch')->get();
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
        $feeDemands = $student->feeDemands()->with('term')->latest('due_date')->get();
        $finance = $this->studentFinance($student);
        $feeDue = $finance['total_billed'];
        $feePaid = $finance['paid'];
        $balance = $finance['balance'];

        return view('parent.fees', compact('parent', 'student', 'payments', 'feeDemands', 'finance', 'feeDue', 'feePaid', 'balance'));
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

    private function studentFinance(Student $student): array
    {
        $demands = FeeDemand::where('student_id', $student->id)->get();
        $activeDemands = $demands->whereIn('status', self::ACTIVE_DEMAND_STATUSES);

        $totalDemanded = $demands->sum(fn($demand) => (float) $demand->final_amount);
        $activePenalty = $activeDemands->sum(fn($demand) => (float) ($demand->penalty_amount ?? 0));
        $balance = $activeDemands->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));
        $paid = FeePayment::where('student_id', $student->id)->where('status', 'paid')->sum('amount_paid');
        $overdueCount = $activeDemands->filter(fn($demand) => $this->isDemandOverdue($demand))->count();
        $nextDueDate = $activeDemands->pluck('due_date')->filter()->sort()->first();

        return [
            'total_billed' => $totalDemanded + $activePenalty,
            'paid' => (float) $paid,
            'balance' => $balance,
            'open_demand_count' => $activeDemands->count(),
            'overdue_count' => $overdueCount,
            'next_due_date' => $nextDueDate,
        ];
    }

    private function studentPriority(?int $attendancePct, array $finance): array
    {
        if ($attendancePct !== null && $attendancePct < 75) {
            return [
                'level' => 'danger',
                'title' => 'Attendance needs attention',
                'body' => "Attendance is {$attendancePct}%. Review subject-wise attendance and contact the mentor if needed.",
                'route' => null,
                'action' => 'Review Attendance',
                'type' => 'attendance',
            ];
        }

        if ($finance['overdue_count'] > 0) {
            return [
                'level' => 'danger',
                'title' => 'Fee demand is overdue',
                'body' => 'There are overdue fee demands. Review the balance and recent receipts.',
                'route' => null,
                'action' => 'Review Fees',
                'type' => 'fees',
            ];
        }

        if ($finance['balance'] > 0) {
            return [
                'level' => 'warning',
                'title' => 'Fee balance is open',
                'body' => 'A fee balance remains open. Track the due date and payment history.',
                'route' => null,
                'action' => 'Review Fees',
                'type' => 'fees',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No urgent action',
            'body' => 'Attendance, fee balance, and recent academic signals are currently clear.',
            'route' => null,
            'action' => 'View Details',
            'type' => 'children',
        ];
    }

    private function isDemandOverdue(FeeDemand $demand): bool
    {
        return $demand->status === 'overdue'
            || ($demand->status === 'pending'
                && $demand->due_date
                && $demand->due_date->lt(now()->startOfDay()));
    }
}
