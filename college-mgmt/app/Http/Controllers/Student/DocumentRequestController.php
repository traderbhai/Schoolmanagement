<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentRequestController extends Controller {
    const TYPES = ['bonafide','fee_letter','character','migration','noc','id_card'];

    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $requests = DocumentRequest::where('student_id', $student->id)
            ->orderByDesc('created_at')->paginate(15);
        $documentPriority = $this->documentPriority($requests->getCollection());

        return view('student.documents.index', compact('requests', 'documentPriority'));
    }

    public function create() {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.documents.index')
                ->with('error', 'New document requests are available only for active students. Contact the administration office if you need archived records.');
        }

        $types = self::TYPES;
        return view('student.documents.create', compact('types'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        if ($student->status !== 'active') {
            return redirect()->route('student.documents.index')
                ->with('error', 'New document requests are available only for active students. Contact the administration office if you need archived records.');
        }

        $data = $request->validate([
            'document_type'   => 'required|in:' . implode(',', self::TYPES),
            'purpose'         => 'required|string|max:255',
            'additional_info' => 'nullable|string|max:1000',
        ]);

        $existingOpenRequest = DocumentRequest::where('student_id', $student->id)
            ->where('document_type', $data['document_type'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existingOpenRequest) {
            return back()
                ->withInput()
                ->with('error', 'You already have an open request for this document type. Track the existing request before submitting another one.');
        }

        DocumentRequest::create([
            'student_id'      => $student->id,
            'document_type'   => $data['document_type'],
            'purpose'         => $data['purpose'] ?? null,
            'additional_info' => $data['additional_info'] ?? null,
        ]);

        return redirect()->route('student.documents.index')
            ->with('success', 'Document request submitted. You will be notified when it is ready.');
    }

    public function download(DocumentRequest $documentRequest)
    {
        $student = Auth::user()->student;
        abort_unless($student && $documentRequest->student_id === $student->id, 403);
        abort_unless($documentRequest->status === 'ready' && $documentRequest->output_path, 404);
        abort_unless(Storage::disk('local')->exists($documentRequest->output_path), 404);

        return response()->download(
            Storage::disk('local')->path($documentRequest->output_path),
            DocumentRequest::typeLabel($documentRequest->document_type) . '.' . pathinfo($documentRequest->output_path, PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream']
        );
    }

    private function documentPriority($requests): array
    {
        $ready = $requests->first(fn ($request) => $request->status === 'ready' && $request->output_path);
        if ($ready) {
            return [
                'level' => 'success',
                'title' => 'A requested document is ready',
                'body' => 'Download your ready document and verify that all details are correct.',
                'route' => route('student.documents.download', $ready),
                'action' => 'Download Document',
            ];
        }

        $rejected = $requests->firstWhere('status', 'rejected');
        if ($rejected) {
            return [
                'level' => 'danger',
                'title' => 'A document request was rejected',
                'body' => 'Review the notes, correct the purpose or details, and submit a fresh request if needed.',
                'route' => route('student.documents.create'),
                'action' => 'New Request',
            ];
        }

        $openCount = $requests->whereIn('status', ['pending', 'approved'])->count();
        if ($openCount > 0) {
            return [
                'level' => 'info',
                'title' => "{$openCount} document request" . ($openCount === 1 ? '' : 's') . ' in progress',
                'body' => 'Track processing status here. Most requests take two to five working days.',
                'route' => route('student.documents.index'),
                'action' => 'Track Requests',
            ];
        }

        return [
            'level' => 'none',
            'title' => 'No active document requests',
            'body' => 'Request bonafide, fee, character, migration, NOC, or ID documents when needed.',
            'route' => route('student.documents.create'),
            'action' => 'Request Document',
        ];
    }
}
