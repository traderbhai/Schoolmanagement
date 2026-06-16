<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Program;
use App\Models\StudentScholarshipApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentScholarshipApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentScholarshipApplication::with(['student.user', 'student.program', 'scheme', 'reviewer'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('program_id')) {
            $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('program_id', $request->program_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student.user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $applications = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => StudentScholarshipApplication::where('status', 'pending')->count(),
            'shortlisted' => StudentScholarshipApplication::where('status', 'shortlisted')->count(),
            'approved' => StudentScholarshipApplication::where('status', 'approved')->count(),
            'disbursed' => StudentScholarshipApplication::where('status', 'disbursed')->count(),
            'approved_amount' => StudentScholarshipApplication::whereIn('status', ['approved', 'disbursed'])->sum('disbursed_amount'),
        ];

        $programs = Program::where('is_active', true)->orderBy('name')->get();

        return view('admin.student-scholarships.index', compact('applications', 'stats', 'programs'));
    }

    public function shortlist(Request $request, StudentScholarshipApplication $application)
    {
        if ($application->status !== 'pending') {
            return back()->with('error', 'Only pending scholarship applications can be shortlisted.');
        }

        $data = $request->validate([
            'review_note' => 'nullable|string|max:1000',
        ]);

        $application->update([
            'status' => 'shortlisted',
            'reviewed_by' => Auth::id(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        $this->notifyStudent($application->fresh(['student.user', 'scheme']), 'Scholarship application shortlisted');

        return back()->with('success', 'Scholarship application shortlisted.');
    }

    public function approve(Request $request, StudentScholarshipApplication $application)
    {
        if (! in_array($application->status, ['pending', 'shortlisted'], true)) {
            return back()->with('error', 'Only pending or shortlisted scholarship applications can be approved.');
        }

        $data = $request->validate([
            'disbursed_amount' => 'required|numeric|min:1',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $scheme = $application->scheme;
        if ($scheme->max_amount > 0 && $data['disbursed_amount'] > $scheme->max_amount) {
            return back()->withErrors(['disbursed_amount' => 'Amount exceeds scheme maximum of Rs. ' . number_format((float) $scheme->max_amount, 2)]);
        }

        if ($scheme->available_seats !== null && $scheme->seatsRemaining() <= 0) {
            return back()->withErrors(['scholarship' => 'No seats remaining for this scholarship scheme.']);
        }

        $application->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'review_note' => $data['review_note'] ?? null,
            'disbursed_amount' => $data['disbursed_amount'],
        ]);

        $this->notifyStudent($application->fresh(['student.user', 'scheme']), 'Scholarship application approved');

        return back()->with('success', 'Scholarship application approved.');
    }

    public function reject(Request $request, StudentScholarshipApplication $application)
    {
        if ($application->status === 'disbursed') {
            return back()->with('error', 'Disbursed scholarship applications cannot be rejected.');
        }

        $data = $request->validate([
            'review_note' => 'required|string|max:1000',
        ]);

        $application->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'review_note' => $data['review_note'],
            'disbursed_amount' => null,
            'disbursed_at' => null,
        ]);

        $this->notifyStudent($application->fresh(['student.user', 'scheme']), 'Scholarship application rejected');

        return back()->with('success', 'Scholarship application rejected.');
    }

    public function disburse(Request $request, StudentScholarshipApplication $application)
    {
        if ($application->status !== 'approved') {
            return back()->with('error', 'Only approved scholarship applications can be disbursed.');
        }

        $data = $request->validate([
            'disbursement_ref' => 'required|string|max:100',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $note = trim(($application->review_note ? $application->review_note . "\n" : '') . 'Disbursement ref: ' . $data['disbursement_ref']);
        if (! empty($data['review_note'])) {
            $note .= "\n" . $data['review_note'];
        }

        $application->update([
            'status' => 'disbursed',
            'reviewed_by' => Auth::id(),
            'review_note' => $note,
            'disbursed_at' => now(),
        ]);

        $this->notifyStudent($application->fresh(['student.user', 'scheme']), 'Scholarship disbursed');

        return back()->with('success', 'Scholarship marked as disbursed.');
    }

    public function downloadProof(StudentScholarshipApplication $application)
    {
        abort_unless($application->documents_path, 404);
        abort_unless(Storage::disk('local')->exists($application->documents_path), 404);

        return response()->download(
            Storage::disk('local')->path($application->documents_path),
            'scholarship-proof-'.$application->id.'.'.pathinfo($application->documents_path, PATHINFO_EXTENSION),
            ['Content-Type' => 'application/octet-stream']
        );
    }

    private function notifyStudent(StudentScholarshipApplication $application, string $title): void
    {
        $studentUser = $application->student?->user;
        if (! $studentUser) {
            return;
        }

        Notification::create([
            'user_id' => $studentUser->id,
            'title' => $title,
            'message' => ($application->scheme?->name ?? 'Scholarship') . ' is now ' . $application->status . '.',
            'type' => 'scholarship',
            'action_url' => route('student.scholarships.index'),
        ]);
    }
}
