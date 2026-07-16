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
use App\Services\AdmissionAccessPolicyService;
use App\Services\AdmissionNextActionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicantCrmController extends Controller
{
    public function __construct(private AdmissionAccessPolicyService $accessPolicy) {}

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
        $this->accessPolicy->applyApplicantVisibility($query, $request->user());

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->counsellor_id) {
            $query->where('assigned_to', $request->counsellor_id);
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

        $sort = $request->input('sort', 'applied_at');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'application_number' => 'application_number',
            'status' => 'status',
            'applied_at' => 'applied_at',
            'created_at' => 'created_at',
            'priority' => 'priority',
            'sla_due_at' => 'sla_due_at',
        ];
        $query->orderBy($sortMap[$sort] ?? 'applied_at', $direction)->orderBy('id', 'desc');

        $perPage = min(100, max(10, (int) $request->input('per_page', 20)));
        $applicants = $query->paginate($perPage)->withQueryString();
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

        return view('admission.applicants.index', compact('applicants', 'programs', 'batches', 'statuses', 'completenessMap', 'sort', 'direction'));
    }

    public function show(Applicant $applicant, AdmissionNextActionService $nextActions)
    {
        $this->accessPolicy->authorizeViewAssignedUser(Auth::user(), $applicant->assigned_to, false);

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

        $canChangeStatus = $this->accessPolicy->canApproveAdmission(Auth::user());
        $allowedTransitions = self::TRANSITIONS[$applicant->status] ?? [];
        $actionCenter = $nextActions->forApplicant($applicant);

        return view('admission.applicants.show', compact('applicant', 'canChangeStatus', 'allowedTransitions', 'actionCenter'));
    }

    public function updateStatus(Request $request, Applicant $applicant)
    {
        abort_unless($this->accessPolicy->canApproveAdmission($request->user()), 403, 'Only authorized admission leadership can change status.');

        if ($applicant->isEnrolled()) {
            return back()->with('error', 'Completed enrollments are locked. Use the academic student lifecycle or audited cancellation workflow instead of changing applicant status.');
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
        $this->accessPolicy->authorizeViewAssignedUser(Auth::user(), $applicant->assigned_to, false);

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
        $this->accessPolicy->authorizeVerifyAdmissionDocuments($request->user());

        $document->loadMissing('applicant');
        $this->accessPolicy->authorizeViewAssignedUser($request->user(), $document->applicant?->assigned_to, false);

        if ($document->status !== 'pending') {
            return back()->with('error', 'Only pending applicant documents can be verified or rejected.');
        }

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

        $this->accessPolicy->authorizeApproveAdmission($request->user());

        $count = 0;
        foreach ($request->applicant_ids as $id) {
            $applicant = Applicant::find($id);
            if (! $applicant || $applicant->isEnrolled()) {
                continue;
            }

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
        $this->accessPolicy->authorizeViewAssignedUser($request->user(), $applicant->assigned_to, false);

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
        $this->accessPolicy->applyApplicantVisibility($query, $request->user());

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
