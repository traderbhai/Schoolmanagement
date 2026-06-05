<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{FeePayment, FeeStructure, Student, Program, Batch, AdmissionPayment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountsController extends Controller
{
    public function dashboard()
    {
        $totalBilled    = FeeStructure::sum('amount');
        $totalCollected = FeePayment::where('status', 'paid')->sum('amount_paid');
        $outstanding    = max(0, $totalBilled - $totalCollected);

        $overdue = FeePayment::where('status', '!=', 'paid')
            ->where('payment_date', '<', now()->subDays(30))
            ->count();

        $recentPayments = FeePayment::with(['student.user', 'feeStructure'])
            ->where('status', 'paid')
            ->latest('payment_date')
            ->take(10)
            ->get();

        $programs = Program::where('is_active', true)->get()->map(function ($p) {
            $studentIds = Student::where('program_id', $p->id)->pluck('id');
            $p->collected = FeePayment::whereIn('student_id', $studentIds)->where('status', 'paid')->sum('amount_paid');
            $p->student_count = $studentIds->count();
            return $p;
        });

        return view('departmental.accounts.dashboard', compact(
            'totalBilled', 'totalCollected', 'outstanding', 'overdue', 'recentPayments', 'programs'
        ));
    }

    public function feeCollections(Request $request)
    {
        $query = FeePayment::with(['student.user', 'student.program', 'student.batch', 'feeStructure']);

        if ($request->filled('program_id')) {
            $studentIds = Student::where('program_id', $request->program_id)->pluck('id');
            $query->whereIn('student_id', $studentIds);
        }
        if ($request->filled('batch_id')) {
            $studentIds = Student::where('batch_id', $request->batch_id)->pluck('id');
            $query->whereIn('student_id', $studentIds);
        }
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('date_from'))  $query->whereDate('payment_date', '>=', $request->date_from);
        if ($request->filled('date_to'))    $query->whereDate('payment_date', '<=', $request->date_to);

        $payments  = $query->latest('payment_date')->paginate(30)->withQueryString();
        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        $batches   = Batch::orderBy('name')->get();

        return view('departmental.accounts.fee-collections', compact('payments', 'programs', 'batches'));
    }

    public function outstanding()
    {
        $programs = Program::where('is_active', true)->get()->map(function ($p) {
            $students = Student::where('program_id', $p->id)
                ->where('status', 'active')
                ->with(['user'])
                ->get()
                ->map(function ($s) {
                    $due   = FeeStructure::where('program_id', $s->program_id)->sum('amount');
                    $paid  = $s->feePayments()->where('status', 'paid')->sum('amount_paid');
                    $s->amount_due = max(0, $due - $paid);
                    $lastPayment = $s->feePayments()->latest('payment_date')->first();
                    $s->last_payment_date = $lastPayment?->payment_date;
                    return $s;
                })
                ->filter(fn($s) => $s->amount_due > 0);
            $p->outstanding_students = $students;
            return $p;
        })->filter(fn($p) => $p->outstanding_students->count() > 0);

        return view('departmental.accounts.outstanding', compact('programs'));
    }

    public function admissionPayments()
    {
        $payments = AdmissionPayment::with(['applicant.user', 'applicant.program'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(25);

        return view('departmental.accounts.admission-payments', compact('payments'));
    }

    public function reports()
    {
        $programs = Program::where('is_active', true)->get()->map(function ($p) {
            $studentIds = Student::where('program_id', $p->id)->pluck('id');
            $p->total_billed    = FeeStructure::where('program_id', $p->id)->sum('amount');
            $p->total_collected = FeePayment::whereIn('student_id', $studentIds)->where('status', 'paid')->sum('amount_paid');
            $p->outstanding     = max(0, $p->total_billed - $p->total_collected);
            $p->collection_pct  = $p->total_billed > 0 ? round(($p->total_collected / $p->total_billed) * 100) : 0;
            return $p;
        });

        $batches = Batch::with('program')->get()->map(function ($b) {
            $studentIds = Student::where('batch_id', $b->id)->pluck('id');
            $b->total_collected = FeePayment::whereIn('student_id', $studentIds)->where('status', 'paid')->sum('amount_paid');
            $b->student_count   = $studentIds->count();
            return $b;
        });

        return view('departmental.accounts.reports', compact('programs', 'batches'));
    }
}
