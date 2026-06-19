<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AccessControl;
use App\Mail\StudentDocumentRequestUpdated;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\Program;
use App\Services\NotificationService;
use App\Services\StudentDocumentRequestClearanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentDocumentRequestController extends Controller
{
    public function __construct(private StudentDocumentRequestClearanceService $clearance) {}

    public function index(Request $request)
    {
        $this->authorizeDocumentRequests($request);

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
        $this->authorizeDocumentRequests($request);

        if ($documentRequest->status !== 'pending') {
            return back()->with('error', 'Only pending document requests can be approved.');
        }

        if ($blocker = $this->clearance->activeStudentBlocker($documentRequest)) {
            return back()->with('error', $blocker);
        }

        if ($blocker = $this->clearance->nocClearanceBlocker($documentRequest)) {
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
        $this->authorizeDocumentRequests($request);

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
        $this->authorizeDocumentRequests($request);

        if ($documentRequest->status !== 'approved') {
            return back()->with('error', 'Only approved document requests can be marked ready.');
        }

        if ($blocker = $this->clearance->activeStudentBlocker($documentRequest)) {
            return back()->with('error', $blocker);
        }

        if ($blocker = $this->clearance->nocClearanceBlocker($documentRequest)) {
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
        $this->authorizeDocumentRequests(request());

        abort_unless($documentRequest->status === 'ready', 404);
        abort_unless($documentRequest->output_path, 404);
        abort_unless(Storage::disk('local')->exists($documentRequest->output_path), 404);
        abort_if($this->clearance->activeStudentBlocker($documentRequest), 403);
        abort_if($this->clearance->nocClearanceBlocker($documentRequest), 403);

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

    private function authorizeDocumentRequests(Request $request): void
    {
        abort_unless(AccessControl::canManageStudentDocuments($request->user()), 403);
    }

}
