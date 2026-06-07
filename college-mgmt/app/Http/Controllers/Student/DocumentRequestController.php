<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentRequestController extends Controller {
    const TYPES = ['bonafide','fee_letter','character','migration','noc','id_card'];

    public function index() {
        $student = Auth::user()->student;
        abort_unless($student, 403);
        $requests = DocumentRequest::where('student_id', $student->id)
            ->orderByDesc('created_at')->paginate(15);
        return view('student.documents.index', compact('requests'));
    }

    public function create() {
        $types = self::TYPES;
        return view('student.documents.create', compact('types'));
    }

    public function store(Request $request) {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $data = $request->validate([
            'document_type'   => 'required|in:' . implode(',', self::TYPES),
            'purpose'         => 'nullable|string|max:255',
            'additional_info' => 'nullable|string|max:1000',
        ]);

        DocumentRequest::create([
            'student_id'      => $student->id,
            'document_type'   => $data['document_type'],
            'purpose'         => $data['purpose'] ?? null,
            'additional_info' => $data['additional_info'] ?? null,
        ]);

        return redirect()->route('student.documents.index')
            ->with('success', 'Document request submitted. You will be notified when it is ready.');
    }
}
