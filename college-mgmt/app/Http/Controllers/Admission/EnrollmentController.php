<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\{Applicant, EnrollmentConfirmation, Program, Batch, RequiredDocument, Specialization, Term};
use App\Services\EnrollmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = EnrollmentConfirmation::with(['applicant.program', 'applicant.user', 'student.user', 'batch'])
            ->where('status', 'completed')
            ->latest();

        if ($request->filled('program_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $request->program_id));
        }
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        $confirmations = $query->paginate(20)->withQueryString();
        $programs = Program::where('is_active', true)->get();
        $batches  = Batch::orderByDesc('id')->get();

        $totalEnrolled = EnrollmentConfirmation::where('status', 'completed')->count();
        $thisMonth     = EnrollmentConfirmation::where('status', 'completed')
            ->whereMonth('confirmed_at', now()->month)
            ->whereYear('confirmed_at', now()->year)
            ->count();

        return view('admission.enrollment.index', compact(
            'confirmations', 'programs', 'batches', 'totalEnrolled', 'thisMonth'
        ));
    }

    public function create(Applicant $applicant)
    {
        $applicant->load(['program', 'batch', 'documents', 'payments', 'user']);

        $isSelected       = $applicant->status === 'selected';
        $hasVerifiedPayment = $applicant->payments->where('status', 'verified')->isNotEmpty();
        $mandatoryDocumentIds = RequiredDocument::where('program_id', $applicant->program_id)
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->pluck('id');
        $mandatoryDocumentCount = $mandatoryDocumentIds->count();
        $verifiedMandatoryDocumentCount = $mandatoryDocumentCount > 0
            ? $applicant->documents
                ->whereIn('required_document_id', $mandatoryDocumentIds)
                ->where('status', 'verified')
                ->count()
            : 0;
        $hasVerifiedMandatoryDocs = $mandatoryDocumentCount === 0
            || $verifiedMandatoryDocumentCount >= $mandatoryDocumentCount;
        $missingMandatoryDocuments = RequiredDocument::whereIn('id', $mandatoryDocumentIds)
            ->whereNotIn('id', $applicant->documents
                ->where('status', 'verified')
                ->pluck('required_document_id')
                ->filter()
                ->all())
            ->orderBy('sort_order')
            ->get();
        $alreadyEnrolled  = $applicant->enrollmentConfirmation()->exists();
        $canEnroll = $isSelected && $hasVerifiedPayment && $hasVerifiedMandatoryDocs && ! $alreadyEnrolled;

        $specializations = Specialization::all();
        $terms = $applicant->batch_id
            ? Term::where('batch_id', $applicant->batch_id)->orderBy('term_number')->get()
            : collect();

        return view('admission.enrollment.create', compact(
            'applicant', 'isSelected', 'hasVerifiedPayment', 'mandatoryDocumentCount',
            'verifiedMandatoryDocumentCount', 'hasVerifiedMandatoryDocs',
            'missingMandatoryDocuments', 'alreadyEnrolled', 'canEnroll',
            'specializations', 'terms'
        ));
    }

    public function store(Request $request, Applicant $applicant)
    {
        $request->validate([
            'roll_number' => 'required|string|max:50',
            'specialization_id' => 'nullable|exists:specializations,id',
            'term_id'     => 'nullable|exists:terms,id',
            'notes'       => 'nullable|string|max:1000',
        ]);

        try {
            $service = new EnrollmentService();
            $confirmation = $service->enroll($applicant, $request->only(['roll_number', 'specialization_id', 'term_id', 'notes']), auth()->id());

            return redirect()->route('admission.enrollment.show', $confirmation)
                ->with('success', 'Student enrolled successfully! Enrollment number: ' . $confirmation->enrollment_number);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Enrollment failed: ' . $e->getMessage());
        }
    }

    public function show(EnrollmentConfirmation $confirmation)
    {
        $confirmation->load(['applicant.program', 'applicant.batch', 'applicant.user', 'student.user', 'confirmedBy', 'batch', 'term']);
        return view('admission.enrollment.show', compact('confirmation'));
    }

    public function printLetter(EnrollmentConfirmation $confirmation)
    {
        $confirmation->load(['applicant.program', 'applicant.batch', 'applicant.payments', 'student.user', 'confirmedBy', 'batch', 'term']);
        $pdf = Pdf::loadView('admission.enrollment.letter', compact('confirmation'));
        return $pdf->download('enrollment-letter-' . $confirmation->enrollment_number . '.pdf');
    }
}
