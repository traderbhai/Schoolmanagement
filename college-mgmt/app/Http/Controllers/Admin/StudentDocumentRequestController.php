<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\StudentDocumentRequestUpdated;
use App\Models\DocumentRequest;
use App\Models\FeeDemand;
use App\Models\HostelFeeDemand;
use App\Models\Notification;
use App\Models\Program;
use App\Services\LibraryFineService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentDocumentRequestController extends Controller
{
    private const ACTIVE_DEMAND_STATUSES = ['pending', 'partially_paid', 'overdue'];

    public function __construct(private LibraryFineService $libraryFineService) {}

    public function index(Request $request)
    {
        $query = DocumentRequest::with(['student.user', 'student.program', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('program_id')) {
            $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('program_id', $request->program_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student.user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $requests = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => DocumentRequest::where('status', 'pending')->count(),
            'approved' => DocumentRequest::where('status', 'approved')->count(),
            'ready_today' => DocumentRequest::where('status', 'ready')->whereDate('fulfilled_at', today())->count(),
            'rejected_today' => DocumentRequest::where('status', 'rejected')->whereDate('updated_at', today())->count(),
        ];

        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $types = \App\Http\Controllers\Student\DocumentRequestController::TYPES;

        return view('admin.document-requests.index', compact('requests', 'stats', 'programs', 'types'));
    }

    public function approve(Request $request, DocumentRequest $documentRequest)
    {
        if ($documentRequest->status !== 'pending') {
            return back()->with('error', 'Only pending document requests can be approved.');
        }

        if ($blocker = $this->nocClearanceBlocker($documentRequest)) {
            return back()->with('error', $blocker);
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $documentRequest->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->notifyStudent($documentRequest->fresh(['student.user']), 'Document request approved');

        return back()->with('success', 'Document request approved for processing.');
    }

    public function reject(Request $request, DocumentRequest $documentRequest)
    {
        if (! in_array($documentRequest->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Only pending or approved document requests can be rejected.');
        }

        $data = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $documentRequest->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'notes' => $data['notes'],
            'fulfilled_at' => null,
            'output_path' => null,
        ]);

        $this->notifyStudent($documentRequest->fresh(['student.user']), 'Document request rejected');

        return back()->with('success', 'Document request rejected with staff notes.');
    }

    public function fulfill(Request $request, DocumentRequest $documentRequest)
    {
        if (! in_array($documentRequest->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Only pending or approved document requests can be marked ready.');
        }

        if ($blocker = $this->nocClearanceBlocker($documentRequest)) {
            return back()->with('error', $blocker);
        }

        $data = $request->validate([
            'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($documentRequest->output_path) {
            Storage::disk('local')->delete($documentRequest->output_path);
        }

        $path = $data['document_file']->store('student-documents/' . $documentRequest->student_id, 'local');

        $documentRequest->update([
            'status' => 'ready',
            'reviewed_by' => Auth::id(),
            'notes' => $data['notes'] ?? $documentRequest->notes,
            'fulfilled_at' => now(),
            'output_path' => $path,
        ]);

        $this->notifyStudent($documentRequest->fresh(['student.user']), 'Document ready for download');

        return back()->with('success', 'Document uploaded and marked ready for the student.');
    }

    public function download(DocumentRequest $documentRequest)
    {
        abort_unless($documentRequest->output_path, 404);
        abort_unless(Storage::disk('local')->exists($documentRequest->output_path), 404);

        return response()->download(
            Storage::disk('local')->path($documentRequest->output_path),
            DocumentRequest::typeLabel($documentRequest->document_type) . '.' . pathinfo($documentRequest->output_path, PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream']
        );
    }

    private function notifyStudent(DocumentRequest $documentRequest, string $title): void
    {
        $studentUser = $documentRequest->student?->user;
        if (! $studentUser) {
            return;
        }

        $documentName = DocumentRequest::typeLabel($documentRequest->document_type);
        $status = $documentRequest->status === 'approved' ? 'processing' : $documentRequest->status;
        $actionUrl = route('student.documents.index');

        Notification::create([
            'user_id' => $studentUser->id,
            'title' => $title,
            'message' => "{$documentName} request is {$status}.",
            'type' => 'document_request',
            'action_url' => $actionUrl,
        ]);

        NotificationService::send(StudentDocumentRequestUpdated::class, $studentUser, [
            'documentRequest' => $documentRequest,
            'actionUrl' => $actionUrl,
        ]);
    }

    private function nocClearanceBlocker(DocumentRequest $documentRequest): ?string
    {
        if ($documentRequest->document_type !== 'noc') {
            return null;
        }

        $studentUser = $documentRequest->student?->user;
        if (! $studentUser) {
            return 'NOC cannot be processed because the student user record is missing.';
        }

        $openFeeBalance = FeeDemand::where('student_id', $documentRequest->student_id)
            ->whereIn('status', self::ACTIVE_DEMAND_STATUSES)
            ->get(['final_amount', 'penalty_amount'])
            ->sum(fn (FeeDemand $demand) => (float) $demand->final_amount + (float) ($demand->penalty_amount ?? 0));

        if ($openFeeBalance > 0) {
            return 'NOC cannot be processed until fee clearance is complete: INR ' . number_format($openFeeBalance, 2) . ' remains open.';
        }

        $openHostelFeeBalance = (float) HostelFeeDemand::where('student_id', $documentRequest->student_id)
            ->where('status', 'pending')
            ->sum('amount');

        if ($openHostelFeeBalance > 0) {
            return 'NOC cannot be processed until hostel fee clearance is complete: INR ' . number_format($openHostelFeeBalance, 2) . ' remains open.';
        }

        $eligibility = $this->libraryFineService->checkNocEligibility($studentUser->id);

        return $eligibility['eligible']
            ? null
            : 'NOC cannot be processed until library clearance is complete: ' . $eligibility['reason'] . '.';
    }
}
