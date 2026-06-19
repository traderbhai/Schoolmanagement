<?php

namespace App\Http\Controllers\Departmental;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, FeePayment, Student, Program, Batch, AdmissionPayment, FeeDemand};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountsController extends Controller
{
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    public function dashboard()
    {
        $totalDemanded  = FeeDemand::sum('final_amount');
        $totalPenalty   = FeeDemand::whereIn('status', self::ACTIVE_DEMAND_STATUSES)->sum('penalty_amount');
        $totalBilled    = $totalDemanded + $totalPenalty;
        $totalCollected = FeePayment::where('status', 'paid')->sum('amount_paid');
        $outstanding    = FeeDemand::whereIn('status', self::ACTIVE_DEMAND_STATUSES)
            ->get(['final_amount', 'penalty_amount'])
            ->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));

        $overdue = FeeDemand::where('status', 'overdue')
            ->orWhere(fn($q) => $q->where('status', 'pending')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString()))
            ->count();

        $recentPayments = FeePayment::with(['student.user', 'feeStructure'])
            ->where('status', 'paid')
            ->latest('payment_date')
            ->take(10)
            ->get();

        $feeByProgram = \App\Models\FeePayment::join('students', 'fee_payments.student_id', '=', 'students.id')
            ->where('fee_payments.status', 'paid')
            ->selectRaw('students.program_id, SUM(fee_payments.amount_paid) as collected, COUNT(DISTINCT fee_payments.student_id) as student_count')
            ->groupBy('students.program_id')
            ->get()
            ->keyBy('program_id');

        $programs = Program::where('is_active', true)->get()->map(function ($p) use ($feeByProgram) {
            $data = $feeByProgram->get($p->id);
            $p->collected = $data?->collected ?? 0;
            $p->student_count = $data?->student_count ?? 0;
            return $p;
        });

        // Admission-side totals
        $totalAdmissionCollected = AdmissionPayment::where('status', 'verified')->sum('amount_paid');
        $pendingAdmissionVerification = AdmissionPayment::where('status', 'pending')->count();

        // Scholarship disbursements pending
        $pendingScholarshipDisbursements = \App\Models\ApplicantScholarship::where('status', 'awarded')->count();
        $pendingScholarshipAmount = \App\Models\ApplicantScholarship::where('status', 'awarded')->sum('awarded_amount');

        // Overdue fee demands
        $overdueDemandQuery = FeeDemand::where('status', 'overdue')
            ->orWhere(fn($q) => $q->where('status', 'pending')
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString()));

        $overdueDemandsCount = (clone $overdueDemandQuery)->count();
        $overdueDemandsAmount = (clone $overdueDemandQuery)
            ->get(['final_amount', 'penalty_amount'])
            ->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));

        // Phase 6: Enhanced fee management KPIs
        $overdueCount   = $overdueDemandsCount;
        $paidDemands    = FeeDemand::where('status', 'fully_paid')->sum('final_amount');
        $collectionRate = $totalDemanded > 0 ? round(($paidDemands / $totalDemanded) * 100, 1) : 0;

        return view('departmental.accounts.dashboard', compact(
            'totalBilled', 'totalCollected', 'outstanding', 'overdue', 'recentPayments', 'programs',
            'totalAdmissionCollected', 'pendingAdmissionVerification',
            'pendingScholarshipDisbursements', 'pendingScholarshipAmount',
            'overdueDemandsCount', 'overdueDemandsAmount',
            'totalDemanded', 'totalPenalty', 'overdueCount', 'collectionRate'
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
        $outstandingStudents = $this->outstandingStudents();

        $programs = Program::where('is_active', true)->orderBy('name')->get()->map(function ($p) use ($outstandingStudents) {
            $students = $outstandingStudents->where('program_id', $p->id)->values();
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
        $demandByProgram = $this->demandFinancialsBy('program_id');
        $paymentByProgram = $this->paymentFinancialsBy('program_id');

        $programs = Program::where('is_active', true)->orderBy('name')->get()->map(function ($p) use ($demandByProgram, $paymentByProgram) {
            $demand = $demandByProgram->get($p->id);
            $payment = $paymentByProgram->get($p->id);

            $p->total_billed = (float) ($demand?->total_demanded ?? 0) + (float) ($demand?->active_penalty ?? 0);
            $p->total_collected = (float) ($payment?->total_collected ?? 0);
            $p->outstanding = (float) ($demand?->outstanding ?? 0);
            $p->collection_pct = $this->collectionPercentage($p->total_collected, $p->total_billed);

            return $p;
        });

        $demandByBatch = $this->demandFinancialsBy('batch_id');
        $paymentByBatch = $this->paymentFinancialsBy('batch_id');

        $batches = Batch::with('program')->orderBy('name')->get()->map(function ($b) use ($demandByBatch, $paymentByBatch) {
            $demand = $demandByBatch->get($b->id);
            $payment = $paymentByBatch->get($b->id);

            $b->total_billed = (float) ($demand?->total_demanded ?? 0) + (float) ($demand?->active_penalty ?? 0);
            $b->total_collected = (float) ($payment?->total_collected ?? 0);
            $b->outstanding = (float) ($demand?->outstanding ?? 0);
            $b->collection_pct = $this->collectionPercentage($b->total_collected, $b->total_billed);
            $b->student_count = Student::where('batch_id', $b->id)->where('status', 'active')->count();

            return $b;
        });

        return view('departmental.accounts.reports', compact('programs', 'batches'));
    }

    public function demandLetter(FeeDemand $feeDemand)
    {
        $feeDemand->load(['student.user', 'student.program', 'term']);

        if (! $feeDemand->student || $feeDemand->student->status !== 'active') {
            return redirect()
                ->route('accounts.outstanding')
                ->with('error', 'Demand letters are available only for active students.');
        }

        if (! in_array($feeDemand->status, self::ACTIVE_DEMAND_STATUSES, true)) {
            return redirect()
                ->route('accounts.outstanding')
                ->with('error', 'Demand letters are available only for open outstanding fee demands.');
        }

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
        $this->recordExportActivity('fee collections', $payments->count(), $request);

        $filename = 'fee-collections-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student Name', 'Program', 'Batch', 'Fee Structure', 'Amount Paid (Rs.)', 'Status', 'Payment Date']);
            foreach ($payments as $p) {
                fputcsv($out, [
                    $p->student->user->name ?? '-',
                    $p->student->program->name ?? '-',
                    $p->student->batch->name ?? '-',
                    $p->feeStructure->name ?? '-',
                    number_format($p->amount_paid, 2),
                    $p->status,
                    $p->payment_date?->format('d M Y') ?? '-',
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
        $this->recordExportActivity('admission payments', $payments->count(), $request);

        $filename = 'admission-payments-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Applicant Name', 'Application No.', 'Program', 'Reference No.', 'Method', 'Amount Paid (Rs.)', 'Verified At']);
            foreach ($payments as $p) {
                fputcsv($out, [
                    $p->applicant->user->name ?? '-',
                    $p->applicant->application_number,
                    $p->applicant->program->name ?? '-',
                    $p->reference_number ?? '-',
                    ucfirst(str_replace('_', ' ', $p->payment_method ?? '-')),
                    number_format($p->amount_paid, 2),
                    $p->verified_at?->format('d M Y, h:i A') ?? '-',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function exportOutstanding(Request $request)
    {
        $students = $this->outstandingStudents();
        $this->recordExportActivity('outstanding fees', $students->count(), $request);

        $filename = 'outstanding-fees-' . now()->format('Ymd') . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student Name', 'Program', 'Batch', 'Outstanding Amount (Rs.)']);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->user->name ?? '-',
                    $s->program->name ?? '-',
                    $s->batch->name ?? '-',
                    number_format($s->amount_due, 2),
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    private function outstandingStudents()
    {
        $lastPayments = FeePayment::selectRaw('student_id, MAX(payment_date) as last_payment')
            ->where('status', 'paid')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        return FeeDemand::with(['student.user', 'student.program', 'student.batch'])
            ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
            ->whereHas('student', fn($q) => $q->where('status', 'active'))
            ->get()
            ->groupBy('student_id')
            ->map(function ($demands) use ($lastPayments) {
                $student = $demands->first()->student;
                $student->amount_due = $demands->sum(fn($demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));
                $student->oldest_due_date = $demands->pluck('due_date')->filter()->sort()->first();
                $student->open_demand_count = $demands->count();
                $student->overdue_demand_count = $demands->filter(fn($demand) => $this->isDemandOverdue($demand))->count();
                $lastPayment = $lastPayments->get($student->id)?->last_payment;
                $student->last_payment_date = $lastPayment ? Carbon::parse($lastPayment) : null;

                return $student;
            })
            ->filter(fn($student) => $student->amount_due > 0)
            ->sortBy(fn($student) => [
                $student->program?->name ?? '',
                $student->user?->name ?? '',
            ])
            ->values();
    }

    private function isDemandOverdue(FeeDemand $demand): bool
    {
        return $demand->status === 'overdue'
            || ($demand->status === 'pending'
                && $demand->due_date
                && $demand->due_date->lt(now()->startOfDay()));
    }

    private function demandFinancialsBy(string $studentColumn)
    {
        $activeStatuses = "'" . implode("','", self::ACTIVE_DEMAND_STATUSES) . "'";

        return FeeDemand::join('students', 'fee_demands.student_id', '=', 'students.id')
            ->select("students.{$studentColumn}")
            ->selectRaw('SUM(fee_demands.final_amount) as total_demanded')
            ->selectRaw("SUM(CASE WHEN fee_demands.status IN ({$activeStatuses}) THEN COALESCE(fee_demands.penalty_amount, 0) ELSE 0 END) as active_penalty")
            ->selectRaw("SUM(CASE WHEN fee_demands.status IN ({$activeStatuses}) THEN fee_demands.final_amount + COALESCE(fee_demands.penalty_amount, 0) ELSE 0 END) as outstanding")
            ->groupBy("students.{$studentColumn}")
            ->get()
            ->keyBy($studentColumn);
    }

    private function paymentFinancialsBy(string $studentColumn)
    {
        return FeePayment::join('students', 'fee_payments.student_id', '=', 'students.id')
            ->where('fee_payments.status', 'paid')
            ->select("students.{$studentColumn}")
            ->selectRaw('SUM(fee_payments.amount_paid) as total_collected')
            ->groupBy("students.{$studentColumn}")
            ->get()
            ->keyBy($studentColumn);
    }

    private function collectionPercentage(float $collected, float $billed): int
    {
        return $billed > 0 ? min(100, (int) round(($collected / $billed) * 100)) : 0;
    }

    private function recordExportActivity(string $surface, int $rowCount, Request $request): void
    {
        $filters = $request->query();
        $filterSummary = empty($filters) ? 'none' : json_encode($filters, JSON_UNESCAPED_SLASHES);

        ActivityLog::record('export', "Accounts {$surface} exported: {$rowCount} rows; filters={$filterSummary}");
    }
}
