<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationRejected;
use App\Mail\ApplicationSelected;
use App\Mail\ApplicationShortlisted;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\AdmissionTeamNote;
use App\Models\CounsellingLog;
use App\Models\Program;
use App\Models\Batch;
use App\Services\DepartmentHierarchyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicantCrmController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

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
        $this->hierarchy->applyApplicantVisibility($query, $request->user(), 'ADM');

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
            $query->where(function ($scope) use ($search) {
                $scope->whereHas('user', fn($q) => $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%"))
                    ->orWhere('application_number', 'like', "%$search%");
            });
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

        // Compute completeness scores for displayed applicants
        $completenessMap = [];
        foreach ($applicants as $applicant) {
            $score = 0;
            foreach (['personal_data', 'academic_data', 'family_data', 'additional_data'] as $field) {
                if (!empty($applicant->$field)) $score += 15;
            }
            if ($applicant->registration_fee_paid_at) $score += 20;
            if ($applicant->documents()->exists()) $score += 10;
            if ($applicant->status !== 'draft') $score += 10;
            $completenessMap[$applicant->id] = min(100, $score);
        }

        return view('admission.applicants.index', compact('applicants', 'programs', 'batches', 'statuses', 'completenessMap'));
    }

    public function show(Applicant $applicant)
    {
        if (!$this->hierarchy->canViewAssignedUser(Auth::user(), 'ADM', $applicant->assigned_to, false)) {
            abort(403);
        }

        $applicant->load([
            'user', 'program', 'batch',
            'documents.requiredDocument',
            'counsellingLogs.loggedBy',
            'teamNotes.user',
            'assignmentEvents.fromUser',
            'assignmentEvents.toUser',
            'assignmentEvents.assignedBy',
            'tags',
        ]);

        $canChangeStatus = $this->hierarchy->canApproveAdmission(Auth::user());
        $allowedTransitions = self::TRANSITIONS[$applicant->status] ?? [];

        return view('admission.applicants.show', compact('applicant', 'canChangeStatus', 'allowedTransitions'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        if (!$this->hierarchy->canApproveAdmission($request->user())) {
            abort(403, 'Only authorized admission leadership can change status.');
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

        app(\App\Services\AdmissionNotificationService::class)
            ->notifyApplicantStatusChanged($applicant->fresh(), $request->status);

        return back()->with('success', 'Applicant status updated to ' . ucfirst(str_replace('_', ' ', $request->status)));
    }

    public function storeCounsellingLog(Request $request, Applicant $applicant)
    {
        if (!$this->hierarchy->canViewAssignedUser(Auth::user(), 'ADM', $applicant->assigned_to, false)) {
            abort(403);
        }

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

        if (!$this->hierarchy->canApproveAdmission($request->user())) {
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
        if (!$this->hierarchy->canViewAssignedUser($request->user(), 'ADM', $applicant->assigned_to, false)) {
            abort(403);
        }

        $request->validate(['note' => 'required|string']);

        AdmissionTeamNote::create([
            'applicant_id' => $applicant->id,
            'user_id'      => Auth::id(),
            'note'         => $request->note,
        ]);

        return back()->with('success', 'Note added.');
    }

    public function exportCsv(Request $request)
    {
        $query = Applicant::with(['user', 'program', 'batch']);

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from) {
            $query->whereDate('applied_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('applied_at', '<=', $request->date_to);
        }

        $applicants = $query->orderBy('created_at', 'desc')->get();

        $filename = 'applicants-export-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($applicants) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Application No', 'Name', 'Email', 'Phone', 'Program', 'Batch',
                'Status', 'Category', 'Entrance Exam', 'Score', 'Rank',
                'Registration Fee Paid', 'Applied At', 'Created At']);
            foreach ($applicants as $applicant) {
                fputcsv($handle, [
                    $applicant->application_number,
                    $applicant->user->name ?? '',
                    $applicant->user->email ?? '',
                    $applicant->user->phone ?? '',
                    $applicant->program->name ?? '',
                    $applicant->batch->name ?? '',
                    $applicant->status,
                    $applicant->category_label ?? $applicant->category ?? '',
                    $applicant->entrance_exam_name ?? '',
                    $applicant->entrance_exam_score ?? '',
                    $applicant->entrance_exam_rank ?? '',
                    $applicant->registration_fee_paid_at ? 'Yes' : 'No',
                    $applicant->applied_at?->format('Y-m-d H:i') ?? '',
                    $applicant->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
