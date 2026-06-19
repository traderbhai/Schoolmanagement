<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HostelFeeDemand;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    private function student(Request $r)
    {
        return $r->user()->student()->with('course', 'department')->firstOrFail();
    }

    public function profile(Request $r)
    {
        $s = $this->student($r);
        return response()->json([
            'name' => $r->user()->name,
            'email' => $r->user()->email,
            'enrollment_number' => $s->enrollment_number,
            'roll_number' => $s->roll_number,
            'course' => $s->course->name ?? null,
            'department' => $s->department->name ?? null,
            'current_semester' => $s->current_semester,
            'status' => $s->status,
        ]);
    }

    public function attendance(Request $r)
    {
        $s = $this->student($r);
        $records = $this->publishedAttendanceQuery($s)
            ->with('timetableEntry.subject')
            ->latest()
            ->take(50)
            ->get();
        $total = $records->count();
        $present = $records->where('status', 'present')->count();
        return response()->json([
            'overall_percentage' => $total > 0 ? round($present / $total * 100, 1) : 0,
            'total_classes' => $total,
            'present' => $present,
            'records' => $records->map(fn($a) => [
                'date' => $a->date,
                'subject' => optional(optional($a->timetableEntry)->subject)->name,
                'status' => $a->status,
            ]),
        ]);
    }

    private function publishedAttendanceQuery(\App\Models\Student $student)
    {
        $subjectIds = $this->enrolledSubjectIds($student);

        return $student->attendances()
            ->when($subjectIds === [], fn($query) => $query->whereRaw('1 = 0'))
            ->whereHas('timetableEntry', function ($query) use ($student, $subjectIds) {
                $query->whereIn('subject_id', $subjectIds)
                    ->where('is_active', true)
                    ->where('status', 'published')
                    ->where(function ($versionQuery) {
                        $versionQuery->whereNull('timetable_version_id')
                            ->orWhereHas('version', fn($version) => $version->where('status', 'published'));
                    })
                    ->when($student->program_id, fn($scope) => $scope->where(function ($programScope) use ($student) {
                        $programScope->whereNull('program_id')->orWhere('program_id', $student->program_id);
                    }))
                    ->when($student->batch_id, fn($scope) => $scope->where(function ($batchScope) use ($student) {
                        $batchScope->whereNull('batch_id')->orWhere('batch_id', $student->batch_id);
                    }))
                    ->when($student->current_term_id, fn($scope) => $scope->where(function ($termScope) use ($student) {
                        $termScope->whereNull('term_id')->orWhere('term_id', $student->current_term_id);
                    }));
            });
    }

    private function enrolledSubjectIds(\App\Models\Student $student): array
    {
        return \App\Models\StudentSubjectEnrollment::where('student_id', $student->id)
            ->where('status', 'active')
            ->when($student->current_term_id, fn($query) => $query->where('term_id', $student->current_term_id))
            ->pluck('subject_id')
            ->merge(\App\Models\Enrollment::where('student_id', $student->id)
                ->whereIn('status', ['active', 'enrolled'])
                ->when($student->current_term_id, fn($query) => $query->where('term_id', $student->current_term_id))
                ->pluck('subject_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function results(Request $r)
    {
        $s = $this->student($r);
        $results = $s->examResults()
            ->whereHas('exam', function ($query) use ($s) {
                $query->whereNotNull('published_at')
                    ->where(function ($eligibleExam) use ($s) {
                        $eligibleExam->whereExists(function ($subquery) use ($s) {
                            $subquery->selectRaw('1')
                                ->from('student_subject_enrollments')
                                ->where('student_subject_enrollments.student_id', $s->id)
                                ->whereColumn('student_subject_enrollments.subject_id', 'exams.subject_id')
                                ->where('student_subject_enrollments.status', 'active')
                                ->where(function ($termQuery) {
                                    $termQuery->whereNull('exams.term_id')
                                        ->orWhereNull('student_subject_enrollments.term_id')
                                        ->orWhereColumn('student_subject_enrollments.term_id', 'exams.term_id');
                                });
                        })->orWhereExists(function ($subquery) use ($s) {
                            $subquery->selectRaw('1')
                                ->from('enrollments')
                                ->where('enrollments.student_id', $s->id)
                                ->whereColumn('enrollments.subject_id', 'exams.subject_id')
                                ->whereIn('enrollments.status', ['active', 'enrolled'])
                                ->where(function ($termQuery) {
                                    $termQuery->where(function ($q) {
                                        $q->whereNotNull('exams.term_id')
                                            ->whereColumn('enrollments.term_id', 'exams.term_id');
                                    })->orWhere(function ($q) {
                                        $q->whereNull('exams.term_id')
                                            ->whereColumn('enrollments.semester_id', 'exams.semester_id');
                                    })->orWhereNull('enrollments.term_id');
                                });
                        });
                    });
            })
            ->with('exam.subject', 'exam.semester')
            ->get();

        return response()->json($results->map(fn($res) => [
            'subject' => optional(optional($res->exam)->subject)->name,
            'semester' => optional(optional($res->exam)->semester)->name,
            'marks_obtained' => $res->marks_obtained,
            'grade' => $res->grade,
            'is_absent' => $res->is_absent,
        ]));
    }

    public function fees(Request $r)
    {
        $s = $this->student($r);
        $payments = $s->feePayments()->with('feeStructure')->where('status', 'paid')->get();
        $reportedPaid = null;
        $demands = $s->feeDemands()->get();
        $activeDemands = $demands->whereIn('status', ['pending', 'partially_paid', 'overdue']);
        $hostelDemands = HostelFeeDemand::with('allocation.room.block')
            ->where('student_id', $s->id)
            ->get();
        $activeHostelDemands = $hostelDemands->where('status', 'pending');
        $hostelBilled = $hostelDemands->sum(fn($demand) => (float) $demand->amount);
        $hostelBalance = $activeHostelDemands->sum(fn($demand) => (float) $demand->amount);

        if ($demands->isNotEmpty()) {
            $totalDue = $demands->sum(fn($demand) => (float) $demand->final_amount)
                + $activeDemands->sum(fn($demand) => (float) ($demand->penalty_amount ?? 0));
            $balance = $activeDemands->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));
        } else {
            $currentAcademicYear = \App\Models\AcademicYear::where('is_current', true)->first();
            $feeStructures = \App\Models\FeeStructure::where('course_id', $s->course_id)
                ->when($currentAcademicYear, fn($query) => $query->where('academic_year_id', $currentAcademicYear->id))
                ->get();
            $totalDue = $feeStructures->sum('amount');
            $structureIds = $feeStructures->pluck('id');
            $reportedPaid = $structureIds->isEmpty()
                ? 0
                : $payments->whereIn('fee_structure_id', $structureIds)->sum('amount_paid');
            $balance = max(0, $totalDue - $reportedPaid);
        }
        $totalDue += $hostelBilled;
        $balance += $hostelBalance;

        $totalPaid = $reportedPaid ?? $payments->sum('amount_paid');
        return response()->json([
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'hostel_balance' => $hostelBalance,
            'payments' => $payments->map(fn($p) => [
                'receipt_number' => $p->receipt_number,
                'fee_type' => optional($p->feeStructure)->fee_type,
                'amount' => $p->amount_paid,
                'date' => $p->payment_date,
                'method' => $p->payment_method,
                'status' => $p->status,
            ]),
            'hostel_demands' => $hostelDemands->map(fn($demand) => [
                'month' => $demand->month,
                'amount' => (float) $demand->amount,
                'due_date' => $demand->due_date,
                'status' => $demand->status,
                'room' => trim(($demand->allocation?->room?->block?->name ?? 'Hostel') . ' / Room ' . ($demand->allocation?->room?->room_number ?? '-')),
            ]),
        ]);
    }
}
