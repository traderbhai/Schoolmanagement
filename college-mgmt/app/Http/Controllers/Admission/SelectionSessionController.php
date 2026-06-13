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

class SelectionSessionController extends Controller
{
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

        $data['created_by'] = auth()->id();
        $data['status']     = $data['status'] ?? 'scheduled';

        $session = SelectionSession::create($data);

        if ($request->boolean('auto_assign')) {
            $applicantsQuery = Applicant::where('program_id', $request->program_id)
                ->where('status', 'shortlisted');
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
        $session->load(['step', 'program', 'batch', 'conductedBy', 'sessionApplicants.applicant.user']);

        // Stats
        $stats = [
            'total'   => $session->sessionApplicants->count(),
            'present' => $session->sessionApplicants->where('attendance_status', 'present')->count(),
            'absent'  => $session->sessionApplicants->where('attendance_status', 'absent')->count(),
            'pending' => $session->sessionApplicants->where('attendance_status', 'pending')->count(),
            'excused' => $session->sessionApplicants->where('attendance_status', 'excused')->count(),
        ];

        // Shortlisted applicants not yet assigned
        $assignedIds = $session->sessionApplicants->pluck('applicant_id');
        $availableApplicants = Applicant::with('user')
            ->where('program_id', $session->program_id)
            ->where('status', 'shortlisted')
            ->whereNotIn('id', $assignedIds)
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

        $session->update($data);

        return redirect()->route('admission.sessions.show', $session)->with('success', 'Session updated successfully.');
    }

    public function destroy(SelectionSession $session)
    {
        $hasAttendance = $session->sessionApplicants()
            ->where('attendance_status', '!=', 'pending')
            ->exists();

        if ($hasAttendance) {
            return back()->with('error', 'Cannot delete session: attendance has already been recorded.');
        }

        $session->delete();

        return redirect()->route('admission.sessions.index')->with('success', 'Session deleted.');
    }

    public function assignApplicants(Request $request, SelectionSession $session)
    {
        $request->validate([
            'applicant_ids'   => 'required|array',
            'applicant_ids.*' => 'exists:applicants,id',
        ]);

        $session->load(['step']);
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
        $request->validate([
            'attendance'           => 'required|array',
            'attendance.*'         => 'in:pending,present,absent,excused',
            'panel_number'         => 'nullable|array',
            'panel_number.*'       => 'nullable|integer|min:1',
        ]);

        foreach ($request->attendance as $applicantId => $status) {
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
        if (!app(DepartmentHierarchyService::class)->canApproveAdmission(auth()->user())) {
            return back()->with('error', 'Only authorized admission leadership can complete a session.');
        }

        $session->update(['status' => 'completed']);

        return back()->with('success', 'Session marked as completed.');
    }

    public function dispatchCallLetters(SelectionSession $session)
    {
        $session->load(['sessionApplicants.applicant.user', 'program']);

        $sent = 0;
        $service = app(\App\Services\AdmissionNotificationService::class);

        foreach ($session->sessionApplicants as $sa) {
            $applicant = $sa->applicant;
            if (!$applicant) continue;

            // Create a DB notification for the call letter
            \App\Models\Notification::create([
                'user_id'  => $applicant->user_id,
                'title'    => 'Interview Call Letter',
                'message'  => "You have been scheduled for " . ($session->step->name ?? 'selection') . " on " . ($session->scheduled_date ? \Carbon\Carbon::parse($session->scheduled_date)->format('d M Y') : 'TBD') . " at " . ($session->venue ?? 'venue TBD') . ". Please report 30 minutes early.",
                'type'     => 'call_letter',
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

        return back()->with('success', "Call letters dispatched to {$sent} candidate(s).");
    }
}
