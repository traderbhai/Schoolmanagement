<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Mail\DocumentRejected;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Batch;
use App\Models\DocumentVerificationRequest;
use App\Models\Program;
use App\Services\DepartmentHierarchyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentVerificationController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function pendingQueue(Request $request)
    {
        $query = ApplicantDocument::with(['applicant.user', 'applicant.program', 'applicant.batch', 'requiredDocument'])
            ->where('status', 'pending')
            ->orderByDesc('uploaded_at');

        $query->whereHas('applicant', function ($q) use ($request) {
            $this->hierarchy->applyApplicantVisibility($q, $request->user(), 'ADM');
        });

        if ($request->filled('program_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('program_id', $request->program_id));
        }
        if ($request->filled('batch_id')) {
            $query->whereHas('applicant', fn($q) => $q->where('batch_id', $request->batch_id));
        }
        if ($request->filled('document_name')) {
            $query->whereHas('requiredDocument', fn($q) => $q->where('name', 'like', '%' . $request->document_name . '%'));
        }
        if ($request->filled('uploaded_from')) {
            $query->where('uploaded_at', '>=', $request->uploaded_from);
        }
        if ($request->filled('uploaded_to')) {
            $query->where('uploaded_at', '<=', $request->uploaded_to . ' 23:59:59');
        }

        $documents = $query->paginate(20)->withQueryString();

        $stats = [
            'pending'         => ApplicantDocument::where('status', 'pending')->count(),
            'verified_today'  => ApplicantDocument::where('status', 'verified')->whereDate('verified_at', today())->count(),
            'rejected_today'  => ApplicantDocument::where('status', 'rejected')->whereDate('verified_at', today())->count(),
        ];

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $batches  = Batch::where('is_active', true)->orderBy('name')->get();

        return view('admission.documents.queue', compact('documents', 'stats', 'programs', 'batches'));
    }

    public function verify(Request $request, ApplicantDocument $document)
    {
        $this->guardDocumentScope($document);

        if ($document->status !== 'pending') {
            return back()->with('error', 'Only pending applicant documents can be verified.');
        }

        $document->update([
            'status'      => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        // Check if ALL mandatory documents are verified — auto-advance applicant
        $this->checkAndAdvanceApplicant($document->applicant_id);

        return back()->with('success', 'Document verified successfully.');
    }

    public function reject(Request $request, ApplicantDocument $document)
    {
        $this->guardDocumentScope($document);

        if ($document->status !== 'pending') {
            return back()->with('error', 'Only pending applicant documents can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $document->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'verified_by'      => Auth::id(),
            'verified_at'      => now(),
        ]);

        // Create verification request to notify applicant
        DocumentVerificationRequest::create([
            'applicant_id' => $document->applicant_id,
            'requested_by' => Auth::id(),
            'message'      => $request->rejection_reason,
            'status'       => 'pending',
        ]);

        // Send email notification
        $document->load(['applicant.user', 'requiredDocument']);
        $applicant = $document->applicant;
        if ($applicant && $applicant->user) {
            NotificationService::send(DocumentRejected::class, $applicant->user, [
                'applicant'       => $applicant,
                'document'        => $document,
                'documentName'    => $document->requiredDocument?->name ?? 'Document',
                'rejectionReason' => $request->rejection_reason,
            ]);
        }

        return back()->with('success', 'Document rejected and applicant notified.');
    }

    public function downloadDocument(ApplicantDocument $document)
    {
        $this->authorizeAccess($document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function previewDocument(ApplicantDocument $document)
    {
        $this->authorizeAccess($document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        $mimeType = Storage::disk('local')->mimeType($document->file_path);
        $content  = Storage::disk('local')->get($document->file_path);

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }

    public function bulkVerify(Request $request)
    {
        $request->validate([
            'document_ids'   => 'required|array|min:1',
            'document_ids.*' => 'integer|exists:applicant_documents,id',
        ]);

        $count = 0;
        $applicantIds = [];

        foreach ($request->document_ids as $id) {
            $doc = ApplicantDocument::find($id);
            if ($doc && $doc->status === 'pending') {
                $this->guardDocumentScope($doc);

                $doc->update([
                    'status'      => 'verified',
                    'verified_by' => Auth::id(),
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);
                $applicantIds[] = $doc->applicant_id;
                $count++;
            }
        }

        foreach (array_unique($applicantIds) as $applicantId) {
            $this->checkAndAdvanceApplicant($applicantId);
        }

        return back()->with('success', "{$count} document(s) verified successfully.");
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    private function authorizeAccess(ApplicantDocument $document): void
    {
        $document->loadMissing('applicant');
        $user = Auth::user();

        // Admission team / admin
        if (($user->hasRole('admin') || $this->hierarchy->isAdmissionUser($user))
            && $this->hierarchy->canViewAssignedUser($user, 'ADM', $document->applicant?->assigned_to, false)) {
            return;
        }

        // Own applicant
        $applicant = $user->applicant ?? null;
        if ($applicant && $applicant->id === $document->applicant_id) {
            return;
        }

        abort(403);
    }

    private function guardDocumentScope(ApplicantDocument $document): void
    {
        $document->loadMissing('applicant');

        if (!$this->hierarchy->canVerifyAdmissionDocuments(Auth::user())
            || !$this->hierarchy->canViewAssignedUser(Auth::user(), 'ADM', $document->applicant?->assigned_to, false)) {
            abort(403);
        }
    }

    private function checkAndAdvanceApplicant(int $applicantId): void
    {
        $applicant = Applicant::with('program')->find($applicantId);
        if (! $applicant || $applicant->status !== 'submitted') {
            return;
        }

        $mandatoryDocs = \App\Models\RequiredDocument::where('program_id', $applicant->program_id)
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->pluck('id');

        if ($mandatoryDocs->isEmpty()) {
            return;
        }

        $verifiedMandatory = ApplicantDocument::where('applicant_id', $applicantId)
            ->where('status', 'verified')
            ->whereIn('required_document_id', $mandatoryDocs)
            ->count();

        if ($verifiedMandatory >= $mandatoryDocs->count()) {
            $applicant->update([
                'status'      => 'under_review',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
        }
    }
}
