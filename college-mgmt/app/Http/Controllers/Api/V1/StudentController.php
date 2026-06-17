<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $records = $s->attendances()->with('timetableEntry.subject')->latest()->take(50)->get();
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

    public function results(Request $r)
    {
        $s = $this->student($r);
        $results = $s->examResults()
            ->whereHas('exam', fn($query) => $query->whereNotNull('published_at'))
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
        $payments = $s->feePayments()->with('feeStructure')->get();
        $feeStructures = \App\Models\FeeStructure::where('course_id', $s->course_id)->get();
        $totalDue = $feeStructures->sum('amount');
        $totalPaid = $payments->where('status', 'paid')->sum('amount_paid');
        return response()->json([
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'balance' => max(0, $totalDue - $totalPaid),
            'payments' => $payments->map(fn($p) => [
                'receipt_number' => $p->receipt_number,
                'fee_type' => optional($p->feeStructure)->fee_type,
                'amount' => $p->amount_paid,
                'date' => $p->payment_date,
                'method' => $p->payment_method,
                'status' => $p->status,
            ]),
        ]);
    }
}
