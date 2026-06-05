<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\AdmissionTeamNote;
use App\Models\CounsellingLog;
use App\Models\Program;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicantCrmController extends Controller
{
    // Allowed status transitions
    private const TRANSITIONS = [
        'submitted'    => ['under_review', 'rejected', 'withdrawn'],
        'under_review' => ['shortlisted', 'rejected', 'withdrawn'],
        'shortlisted'  => ['selected', 'rejected', 'withdrawn'],
        'selected'     => ['withdrawn'],
        'rejected'     => [],
        'withdrawn'    => [],
        'draft'        => ['withdrawn'],
    ];

    public function index(Request $request)
    {
        $query = Applicant::with(['user', 'program', 'batch',
            'counsellingLogs' => fn($q) => $q->latest()->limit(1),
        ]);

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"))
                ->orWhere('application_number', 'like', "%$search%");
        }
        if ($request->date_from) {
            $query->whereDate('applied_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('applied_at', '<=', $request->date_to);
        }

        $applicants = $query->latest()->paginate(20)->withQueryString();
        $programs = Program::where('is_active', true)->orderBy('name')->get();
        $batches = Batch::orderBy('name')->get();
        $statuses = ['draft', 'submitted', 'under_review', 'shortlisted', 'selected', 'rejected', 'withdrawn'];

        return view('admission.applicants.index', compact('applicants', 'programs', 'batches', 'statuses'));
    }

    public function show(Applicant $applicant)
    {
        $applicant->load([
            'user', 'program', 'batch',
            'documents.requiredDocument',
            'counsellingLogs.loggedBy',
            'teamNotes.user',
        ]);

        $canChangeStatus = Auth::user()->hasRole('admission_head') || Auth::user()->hasRole('admin');
        $allowedTransitions = self::TRANSITIONS[$applicant->status] ?? [];

        return view('admission.applicants.show', compact('applicant', 'canChangeStatus', 'allowedTransitions'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        if (! (Auth::user()->hasRole('admission_head') || Auth::user()->hasRole('admin'))) {
            abort(403, 'Only Admission Head can change status.');
        }

        $allowed = self::TRANSITIONS[$applicant->status] ?? [];
        $request->validate([
            'status' => ['required', 'in:' . implode(',', $allowed)],
        ]);

        $applicant->update([
            'status'      => $request->status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Applicant status updated to ' . ucfirst(str_replace('_', ' ', $request->status)));
    }

    public function storeCounsellingLog(Request $request, Applicant $applicant)
    {
        $request->validate([
            'interaction_type'   => 'required|in:call,email,whatsapp,walk_in,other',
            'outcome'            => 'required|in:interested,not_interested,callback,enrolled,lost,follow_up',
            'notes'              => 'required|string',
            'next_followup_date' => 'nullable|date',
            'duration_minutes'   => 'nullable|integer|min:1',
        ]);

        CounsellingLog::create([
            'applicant_id'       => $applicant->id,
            'logged_by'          => Auth::id(),
            'interaction_type'   => $request->interaction_type,
            'outcome'            => $request->outcome,
            'notes'              => $request->notes,
            'next_followup_date' => $request->next_followup_date,
            'duration_minutes'   => $request->duration_minutes,
        ]);

        return back()->with('success', 'Interaction logged successfully.');
    }

    public function verifyDocument(Request $request, ApplicantDocument $document)
    {
        $request->validate([
            'action'           => 'required|in:verified,rejected',
            'rejection_reason' => 'required_if:action,rejected|nullable|string',
        ]);

        $document->update([
            'status'           => $request->action,
            'rejection_reason' => $request->action === 'rejected' ? $request->rejection_reason : null,
            'verified_by'      => Auth::id(),
            'verified_at'      => now(),
        ]);

        return back()->with('success', 'Document ' . $request->action . '.');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action'        => 'required|in:under_review,shortlisted,rejected,withdrawn',
            'applicant_ids' => 'required|array',
            'applicant_ids.*' => 'integer|exists:applicants,id',
        ]);

        if (! (Auth::user()->hasRole('admission_head') || Auth::user()->hasRole('admin'))) {
            abort(403);
        }

        $count = 0;
        foreach ($request->applicant_ids as $id) {
            $applicant = Applicant::find($id);
            $allowed = self::TRANSITIONS[$applicant->status] ?? [];
            if (in_array($request->action, $allowed)) {
                $applicant->update([
                    'status'      => $request->action,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
                $count++;
            }
        }

        return back()->with('success', "$count applicant(s) updated to " . ucfirst(str_replace('_', ' ', $request->action)));
    }

    public function storeNote(Request $request, Applicant $applicant)
    {
        $request->validate(['note' => 'required|string']);

        AdmissionTeamNote::create([
            'applicant_id' => $applicant->id,
            'user_id'      => Auth::id(),
            'note'         => $request->note,
        ]);

        return back()->with('success', 'Note added.');
    }
}
