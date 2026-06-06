<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{FeePayment, FeeStructure, Student, Program, Batch, AdmissionPayment, FeeDemand};
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

        // Admission-side totals
        $totalAdmissionCollected = AdmissionPayment::where('status', 'verified')->sum('amount_paid');
        $pendingAdmissionVerification = AdmissionPayment::where('status', 'pending')->count();

        // Scholarship disbursements pending
        $pendingScholarshipDisbursements = \App\Models\ApplicantScholarship::where('status', 'awarded')->count();
        $pendingScholarshipAmount = \App\Models\ApplicantScholarship::where('status', 'awarded')->sum('awarded_amount');

        // Overdue fee demands
        $overdueDemandsCount = FeeDemand::where('status', 'overdue')->count();
        $overdueDemandsAmount = FeeDemand::where('status', 'overdue')->sum('final_amount');

        return view('departmental.accounts.dashboard', compact(
            'totalBilled', 'totalCollected', 'outstanding', 'overdue', 'recentPayments', 'programs',
            'totalAdmissionCollected', 'pendingAdmissionVerification',
            'pendingScholarshipDisbursements', 'pendingScholarshipAmount',
            'overdueDemandsCount', 'overdueDemandsAmount'
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

    public function demandLetter(FeeDemand $feeDemand)
    {
        $feeDemand->load(['student.user', 'student.program', 'term']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'departmental.accounts.demand-letter-pdf',
            compact('feeDemand')
        )->setPaper('a4', 'portrait');

        $filename = 'fee-demand-' . ($feeDemand->student->user->name ?? 'student') . '-' . $feeDemand->term->name . '.pdf';
        return $pdf->stream($filename);
    }

    public function reconciliation(Request $request)
    {
        $programs = \App\Models\Program::where('is_active', true)->orderBy('name')->get();
        $selectedProgram = $request->filled('program_id')
            ? \App\Models\Program::find($request->program_id)
            : null;

        // Verified admission payments grouped by program
        $query = AdmissionPayment::where('status', 'verified')
            ->with(['applicant.program', 'applicant.user']);

        if ($selectedProgram) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $selectedProgram->id));
        }

        $payments = $query->latest('verified_at')->paginate(30)->withQueryString();

        // Summary stats
        $summaryByProgram = AdmissionPayment::where('admission_payments.status', 'verified')
            ->join('applicants', 'admission_payments.applicant_id', '=', 'applicants.id')
            ->join('programs', 'applicants.program_id', '=', 'programs.id')
            ->select('programs.name as program_name', 'programs.id as program_id',
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as payment_count'),
                \Illuminate\Support\Facades\DB::raw('SUM(admission_payments.amount_paid) as total_collected'))
            ->groupBy('programs.id', 'programs.name')
            ->orderBy('programs.name')
            ->get();

        $grandTotal = $summaryByProgram->sum('total_collected');

        return view('departmental.accounts.reconciliation', compact('payments', 'programs', 'selectedProgram', 'summaryByProgram', 'grandTotal'));
    }

    public function exportFeeCollections(Request $request)
    {
        $query = FeePayment::with(['student.user', 'student.program', 'student.batch', 'feeStructure']);
        if ($request->filled('program_id')) {
            $ids = \App\Models\Student::where('program_id', $request->program_id)->pluck('id');
            $query->whereIn('student_id', $ids);
        }
        if ($request->filled('batch_id')) {
            $ids = \App\Models\Student::where('batch_id', $request->batch_id)->pluck('id');
            $query->whereIn('student_id', $ids);
        }
        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('date_from')) $query->whereDate('payment_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('payment_date', '<=', $request->date_to);

        $payments = $query->latest('payment_date')->get();

        $filename = 'fee-collections-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student Name', 'Program', 'Batch', 'Fee Structure', 'Amount Paid (₹)', 'Status', 'Payment Date']);
            foreach ($payments as $p) {
                fputcsv($out, [
                    $p->student->user->name ?? '—',
                    $p->student->program->name ?? '—',
                    $p->student->batch->name ?? '—',
                    $p->feeStructure->name ?? '—',
                    number_format($p->amount_paid, 2),
                    $p->status,
                    $p->payment_date?->format('d M Y') ?? '—',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function exportAdmissionPayments(Request $request)
    {
        $query = AdmissionPayment::with(['applicant.user', 'applicant.program'])
            ->where('status', 'verified');
        if ($request->filled('program_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $request->program_id));
        }
        $payments = $query->latest('verified_at')->get();

        $filename = 'admission-payments-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Applicant Name', 'Application No.', 'Program', 'Reference No.', 'Method', 'Amount Paid (₹)', 'Verified At']);
            foreach ($payments as $p) {
                fputcsv($out, [
                    $p->applicant->user->name ?? '—',
                    $p->applicant->application_number,
                    $p->applicant->program->name ?? '—',
                    $p->reference_number ?? '—',
                    ucfirst(str_replace('_', ' ', $p->payment_method ?? '—')),
                    number_format($p->amount_paid, 2),
                    $p->verified_at?->format('d M Y, h:i A') ?? '—',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function exportOutstanding(Request $request)
    {
        $students = Student::where('status', 'active')
            ->with(['user', 'program', 'batch'])
            ->get()
            ->map(function ($s) {
                $due  = \App\Models\FeeStructure::where('program_id', $s->program_id)->sum('amount');
                $paid = $s->feePayments()->where('status', 'paid')->sum('amount_paid');
                $s->amount_due = max(0, $due - $paid);
                return $s;
            })
            ->filter(fn($s) => $s->amount_due > 0);

        $filename = 'outstanding-fees-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student Name', 'Program', 'Batch', 'Outstanding Amount (₹)']);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->user->name ?? '—',
                    $s->program->name ?? '—',
                    $s->batch->name ?? '—',
                    number_format($s->amount_due, 2),
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
