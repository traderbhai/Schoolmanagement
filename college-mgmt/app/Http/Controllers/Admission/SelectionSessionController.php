<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Mail\SessionScheduled;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\Program;
use App\Models\SelectionProcessStep;
use App\Models\SelectionSession;
use App\Models\SessionApplicant;
use App\Services\DepartmentHierarchyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SelectionSessionController extends Controller
{
    public function __construct(private DepartmentHierarchyService $hierarchy) {}

    public function index(Request $request)
    {
        $query = SelectionSession::with(['step', 'program', 'batch', 'sessionApplicants']);

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }
        if ($request->step_type) {
            $query->whereHas('step', fn($q) => $q->where('type', $request->step_type));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from) {
            $query->where('scheduled_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('scheduled_date', '<=', $request->date_to);
        }

        $all = $query->orderBy('scheduled_date', 'desc')->orderBy('start_time')->get();

        $upcoming = $all->filter(fn($s) => $s->scheduled_date->gte(today()) && in_array($s->status, ['scheduled', 'ongoing']))->sortBy(['scheduled_date', 'start_time']);
        $past     = $all->filter(fn($s) => !($s->scheduled_date->gte(today()) && in_array($s->status, ['scheduled', 'ongoing'])))->sortByDesc('scheduled_date');

        $programs  = Program::where('is_active', true)->orderBy('name')->get();
        $stepTypes = [
            'gd' => 'Group Discussion',
            'pi' => 'Personal Interview',
            'case_analysis' => 'Case Analysis',
            'wat' => 'Written Ability Test',
            'written_test' => 'Written Test',
            'aptitude' => 'Aptitude Test',
            'presentation' => 'Presentation',
            'portfolio_review' => 'Portfolio Review',
            'screening_call' => 'Screening Call',
        ];

        return view('admission.sessions.index', compact('upcoming', 'past', 'programs', 'stepTypes'));
    }

    public function create()
    {
        $programs = Program::where('is_active', true)->with('selectionProcessSteps')->orderBy('name')->get();
        $batches  = Batch::orderBy('name')->get();

        return view('admission.sessions.create', compact('programs', 'batches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'selection_process_step_id' => 'required|exists:selection_process_steps,id',
            'program_id'   => 'required|exists:programs,id',
            'batch_id'     => 'nullable|exists:batches,id',
            'session_name' => 'required|string|max:255',
            'scheduled_date' => 'required|date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'venue'        => 'nullable|string|max:255',
            'max_candidates' => 'nullable|integer|min:1',
            'instructions' => 'nullable|string',
            'status'       => 'nullable|in:scheduled,ongoing,completed,cancelled',
            'conducted_by' => 'nullable|exists:users,id',
        ]);

        $this->validateSessionScope($data);

        $data['created_by'] = auth()->id();
        $data['status']     = $data['status'] ?? 'scheduled';

        $session = SelectionSession::create($data);

        if ($request->boolean('auto_assign')) {
            $applicantsQuery = Applicant::where('program_id', $request->program_id)
                ->where('status', 'shortlisted');
            $this->applySessionApplicantVisibility($applicantsQuery, $request->user());
            if ($request->batch_id) {
                $applicantsQuery->where('batch_id', $request->batch_id);
            }
            foreach ($applicantsQuery->get() as $applicant) {
                SessionApplicant::firstOrCreate([
                    'selection_session_id' => $session->id,
                    'applicant_id'         => $applicant->id,
                ], ['assigned_at' => now()]);
            }
        }

        return redirect()->route('admission.sessions.show', $session)->with('success', 'Session created successfully.');
    }

    public function show(SelectionSession $session)
    {
        $session->load(['step', 'program', 'batch', 'conductedBy']);
        $visibleSessionApplicants = $session->sessionApplicants()
            ->whereHas('applicant', fn ($query) => $this->applySessionApplicantVisibility($query, request()->user()))
            ->with('applicant.user')
            ->get();
        $session->setRelation('sessionApplicants', $visibleSessionApplicants);

        // Stats
        $stats = [
            'total'   => $visibleSessionApplicants->count(),
            'present' => $visibleSessionApplicants->where('attendance_status', 'present')->count(),
            'absent'  => $visibleSessionApplicants->where('attendance_status', 'absent')->count(),
            'pending' => $visibleSessionApplicants->where('attendance_status', 'pending')->count(),
            'excused' => $visibleSessionApplicants->where('attendance_status', 'excused')->count(),
        ];

        // Shortlisted applicants not yet assigned
        $assignedIds = $session->sessionApplicants->pluck('applicant_id');
        $availableApplicants = Applicant::with('user')
            ->where('program_id', $session->program_id)
            ->where('status', 'shortlisted')
            ->whereNotIn('id', $assignedIds)
            ->tap(fn ($query) => $this->applySessionApplicantVisibility($query, request()->user()))
            ->get();

        $panelSummary = app(\App\Services\AdmissionAssessmentPanelService::class)->summaryForSession($session);

        return view('admission.sessions.show', compact('session', 'stats', 'availableApplicants', 'panelSummary'));
    }

    public function edit(SelectionSession $session)
    {
        if ($session->status !== 'scheduled') {
            return redirect()->route('admission.sessions.show', $session)->with('error', 'Only scheduled sessions can be edited.');
        }

        $programs = Program::where('is_active', true)->with('selectionProcessSteps')->orderBy('name')->get();
        $batches  = Batch::orderBy('name')->get();

        return view('admission.sessions.edit', compact('session', 'programs', 'batches'));
    }

    public function update(Request $request, SelectionSession $session)
    {
        if ($session->status !== 'scheduled') {
            return redirect()->route('admission.sessions.show', $session)->with('error', 'Only scheduled sessions can be edited.');
        }

        $data = $request->validate([
            'selection_process_step_id' => 'required|exists:selection_process_steps,id',
            'program_id'   => 'required|exists:programs,id',
            'batch_id'     => 'nullable|exists:batches,id',
            'session_name' => 'required|string|max:255',
            'scheduled_date' => 'required|date',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'venue'        => 'nullable|string|max:255',
            'max_candidates' => 'nullable|integer|min:1',
            'instructions' => 'nullable|string',
            'conducted_by' => 'nullable|exists:users,id',
        ]);

        $this->validateSessionScope($data);

        $session->update($data);

        return redirect()->route('admission.sessions.show', $session)->with('success', 'Session updated successfully.');
    }

    public function destroy(SelectionSession $session)
    {
        if ($this->hasAssessmentHistory($session)) {
            return back()->with('error', 'Cannot delete session: assigned candidates, attendance, panels, scores, or assessment history already exist.');
        }

        $session->delete();

        return redirect()->route('admission.sessions.index')->with('success', 'Session deleted.');
    }

    public function assignApplicants(Request $request, SelectionSession $session)
    {
        if (! in_array($session->status, ['scheduled', 'ongoing'], true)) {
            return back()->with('error', 'Candidates can be assigned only to scheduled or ongoing sessions.');
        }

        $request->validate([
            'applicant_ids'   => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id',
        ]);

        $session->load(['step']);
        $applicants = Applicant::whereIn('id', $request->applicant_ids)
            ->get()
            ->keyBy('id');
        $invalidApplicants = collect($request->applicant_ids)
            ->filter(function ($applicantId) use ($session, $applicants) {
                $applicant = $applicants->get((int) $applicantId);

                return ! $applicant
                    || (int) $applicant->program_id !== (int) $session->program_id
                    || ($session->batch_id && (int) $applicant->batch_id !== (int) $session->batch_id)
                    || $applicant->status !== 'shortlisted';
            });
        $hiddenApplicants = collect($request->applicant_ids)
            ->filter(fn ($applicantId) => ! $this->canViewApplicantId((int) $applicantId, $request->user()));

        if ($invalidApplicants->isNotEmpty()) {
            throw ValidationException::withMessages([
                'applicant_ids' => 'Candidates must be shortlisted applicants for this session program and batch.',
            ]);
        }
        abort_if($hiddenApplicants->isNotEmpty(), 403);

        foreach ($request->applicant_ids as $applicantId) {
            $created = SessionApplicant::firstOrCreate([
                'selection_session_id' => $session->id,
                'applicant_id'         => $applicantId,
            ], ['assigned_at' => now()]);

            if ($created->wasRecentlyCreated) {
                $applicant = Applicant::with('user')->find($applicantId);
                if ($applicant && $applicant->user) {
                    NotificationService::send(SessionScheduled::class, $applicant->user, [
                        'applicant' => $applicant,
                        'session'   => $session,
                    ]);
                }
            }
        }

        return back()->with('success', count($request->applicant_ids) . ' candidate(s) assigned to session.');
    }

    public function removeApplicant(SelectionSession $session, Applicant $applicant)
    {
        $this->guardApplicantScope($applicant);

        if ($session->status !== 'scheduled') {
            return back()->with('error', 'Candidates can be removed only from scheduled sessions before assessment activity starts.');
        }

        $pivot = SessionApplicant::where('selection_session_id', $session->id)
            ->where('applicant_id', $applicant->id)
            ->first();

        if ($pivot && $pivot->attendance_status !== 'pending') {
            return back()->with('error', 'Cannot remove candidate: attendance already recorded.');
        }

        SessionApplicant::where('selection_session_id', $session->id)
            ->where('applicant_id', $applicant->id)
            ->delete();

        return back()->with('success', 'Candidate removed from session.');
    }

    public function markAttendance(Request $request, SelectionSession $session)
    {
        if (! in_array($session->status, ['scheduled', 'ongoing'], true)) {
            return back()->with('error', 'Attendance can be recorded only for scheduled or ongoing sessions.');
        }

        $request->validate([
            'attendance'           => 'required|array',
            'attendance.*'         => 'in:pending,present,absent,excused',
            'panel_number'         => 'nullable|array',
            'panel_number.*'       => 'nullable|integer|min:1',
        ]);

        foreach ($request->attendance as $applicantId => $status) {
            abort_unless($this->canViewApplicantId((int) $applicantId, $request->user()), 403);

            $pivot = SessionApplicant::where('selection_session_id', $session->id)
                ->where('applicant_id', $applicantId)
                ->first();

            if ($pivot) {
                $pivot->attendance_status = $status;
                if (isset($request->panel_number[$applicantId])) {
                    $pivot->panel_number = $request->panel_number[$applicantId] ?: null;
                }
                $pivot->save();
            }
        }

        if ($session->status === 'scheduled') {
            $session->update(['status' => 'ongoing']);
        }

        return back()->with('success', 'Attendance recorded successfully.');
    }

    public function completeSession(SelectionSession $session)
    {
        if (!$this->hierarchy->canApproveAdmission(auth()->user())) {
            return back()->with('error', 'Only authorized admission leadership can complete a session.');
        }

        if (! in_array($session->status, ['scheduled', 'ongoing'], true)) {
            return back()->with('error', 'Only scheduled or ongoing sessions can be completed.');
        }

        if ($session->sessionApplicants()->where('attendance_status', 'pending')->exists()) {
            return back()->with('error', 'Resolve all pending candidate attendance before completing the session.');
        }

        $session->update(['status' => 'completed']);

        return back()->with('success', 'Session marked as completed.');
    }

    public function dispatchCallLetters(SelectionSession $session)
    {
        if (! in_array($session->status, ['scheduled', 'ongoing'], true)) {
            return back()->with('error', 'Call letters can be dispatched only for scheduled or ongoing sessions.');
        }

        $session->load(['program', 'step']);
        $sessionApplicants = $session->sessionApplicants()
            ->whereHas('applicant', fn ($query) => $this->applySessionApplicantVisibility($query, request()->user()))
            ->with('applicant.user')
            ->get();

        $sent = 0;
        $skipped = 0;
        $service = app(\App\Services\AdmissionNotificationService::class);

        foreach ($sessionApplicants as $sa) {
            $applicant = $sa->applicant;
            if (!$this->isEligibleForCallLetter($sa)) {
                $skipped++;
                continue;
            }

            $actionUrl = route('admission.applicants.call-letter', $applicant);
            $existingNotice = \App\Models\Notification::where('user_id', $applicant->user_id)
                ->where('type', 'call_letter')
                ->where('action_url', $actionUrl)
                ->exists();

            if ($existingNotice) {
                $skipped++;
                continue;
            }

            // Create a DB notification for the call letter
            \App\Models\Notification::create([
                'user_id'  => $applicant->user_id,
                'title'    => 'Interview Call Letter',
                'message'  => "You have been scheduled for " . ($session->step->name ?? 'selection') . " on " . ($session->scheduled_date ? \Carbon\Carbon::parse($session->scheduled_date)->format('d M Y') : 'TBD') . " at " . ($session->venue ?? 'venue TBD') . ". Please report 30 minutes early.",
                'type'     => 'call_letter',
                'action_url' => $actionUrl,
                'read_at'  => null,
            ]);

            // Attempt email with the call letter view
            if ($applicant->user && $applicant->user->email) {
                try {
                    \Illuminate\Support\Facades\Mail::send(
                        'admission.call-letters.template',
                        ['applicant' => $applicant, 'session' => $session, 'collegeName' => config('app.name')],
                        fn($m) => $m->to($applicant->user->email)
                                    ->subject('Interview Call Letter — ' . ($session->program->name ?? 'Admission'))
                    );
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Call letter email failed for applicant ' . $applicant->id . ': ' . $e->getMessage());
                }
            }

            $sent++;
        }

        return back()->with('success', "Call letters dispatched to {$sent} candidate(s). {$skipped} skipped.");
    }

    private function validateSessionScope(array $data): void
    {
        $step = SelectionProcessStep::find($data['selection_process_step_id'] ?? null);
        if (! $step || (int) $step->program_id !== (int) ($data['program_id'] ?? 0) || ! $step->is_active) {
            throw ValidationException::withMessages([
                'selection_process_step_id' => 'Selection step must be active and belong to the selected program.',
            ]);
        }

        if (! empty($data['batch_id'])) {
            $validBatch = Batch::where('id', $data['batch_id'])
                ->where('program_id', $data['program_id'])
                ->where('status', 'active')
                ->exists();

            if (! $validBatch) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Selection session batch must be an active batch for the selected program.',
                ]);
            }
        }
    }

    private function hasAssessmentHistory(SelectionSession $session): bool
    {
        return $session->sessionApplicants()->exists()
            || $session->assessmentPanels()->exists()
            || $session->panelAssignments()->exists()
            || \App\Models\ApplicantScore::where('selection_session_id', $session->id)->exists()
            || \App\Models\AdmissionAssessmentArtifact::where('selection_session_id', $session->id)->exists()
            || \App\Models\AdmissionAssessmentLifecycleEvent::where('selection_session_id', $session->id)->exists()
            || \App\Models\AdmissionAssessmentReschedule::where('from_session_id', $session->id)->orWhere('to_session_id', $session->id)->exists();
    }

    private function isEligibleForCallLetter(SessionApplicant $assignment): bool
    {
        $applicant = $assignment->applicant;

        return $applicant
            && $applicant->user_id
            && ! in_array($applicant->status, ['rejected', 'withdrawn', 'enrolled'], true)
            && (int) $applicant->program_id === (int) $assignment->session?->program_id
            && ! in_array($assignment->attendance_status, ['absent', 'excused'], true);
    }

    private function applySessionApplicantVisibility($query, $user): void
    {
        if ($user->hasRole('admin') || $this->hierarchy->canSeeAll($user, 'ADM')) {
            return;
        }

        $visibleUserIds = $this->hierarchy->visibleUserIds($user, 'ADM');

        $query->where(function ($scope) use ($visibleUserIds) {
            $scope->whereIn('assigned_to', $visibleUserIds)
                ->orWhereNull('assigned_to');
        });
    }

    private function guardApplicantScope(Applicant $applicant): void
    {
        abort_unless(
            $this->hierarchy->canViewAssignedUser(request()->user(), 'ADM', $applicant->assigned_to, true),
            403
        );
    }

    private function canViewApplicantId(int $applicantId, $user): bool
    {
        $assignedTo = Applicant::whereKey($applicantId)->value('assigned_to');

        return $this->hierarchy->canViewAssignedUser($user, 'ADM', $assignedTo, true);
    }
}
