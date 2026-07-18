@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><h1 class="h4 mb-1">{{ $title }}</h1><div class="small text-muted">{{ $description }}</div></div>@include('academics.pmc.v004.partials.nav')</div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">PMC record workflow</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Filter scope</span>
            <span class="badge text-bg-light border">2. Review open/critical/overdue</span>
            <span class="badge text-bg-light border">3. Create action for blocker</span>
            <span class="badge text-bg-light border">4. Update owner and evidence</span>
            <span class="badge text-bg-light border">5. Export current view</span>
        </div>
        <div class="text-muted mt-2">Use this list as the source-backed operating register for the selected PMC workflow; keep filters visible before making decisions.</div>
    </div>
    @php
        $summaryLinks = [
            'Total' => url()->current(),
            'Open' => request()->fullUrlWithQuery(['status' => 'open', 'due' => null, 'page' => null]),
            'Critical' => request()->fullUrlWithQuery(['risk_band' => 'critical', 'page' => null]),
            'Overdue' => request()->fullUrlWithQuery(['due' => 'overdue', 'status' => null, 'page' => null]),
        ];
    @endphp
    <div class="row g-2 mb-3">@foreach(['Total'=>$summary['total'],'Open'=>$summary['open'],'Critical'=>$summary['critical'],'Overdue'=>$summary['overdue']] as $label=>$value)<div class="col-6 col-md-3"><a href="{{ $summaryLinks[$label] }}" class="card text-reset text-decoration-none shadow-sm h-100"><div class="card-body py-2"><div class="d-flex justify-content-between gap-2"><div class="small text-muted">{{ $label }}</div><i class="bi bi-arrow-up-right small text-muted"></i></div><div class="h4 mb-0">{{ $value }}</div></div></a></div>@endforeach</div>
    <div class="card shadow-sm mb-3"><div class="card-body py-2"><form class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label small">Search</label><input aria-label="Title or description" class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Title or description"></div><div class="col-md-2"><label class="form-label small">Status</label><select aria-label="Status" class="form-select form-select-sm" name="status"><option value="">All</option>@foreach(['open','pending','blocked','in_progress','done','closed','resolved','published','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str($status)->headline() }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label small">Risk</label><select aria-label="Risk Band" class="form-select form-select-sm" name="risk_band"><option value="">All</option>@foreach(['low','medium','high','critical'] as $risk)<option value="{{ $risk }}" @selected(request('risk_band')===$risk)>{{ ucfirst($risk) }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label small">Due</label><select aria-label="Due" class="form-select form-select-sm" name="due"><option value="">Any</option><option value="overdue" @selected(request('due')==='overdue')>Overdue</option></select></div><div class="col-md-3 d-flex gap-1"><button class="btn btn-sm btn-primary">Filter</button><a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}">Reset</a><a class="btn btn-sm btn-outline-success" href="{{ route('academics.pmc.export', ['report' => $surface] + request()->query()) }}">Export</a></div></form><div class="small text-muted mt-2">Visible filter summary: {{ $filterSummary }}</div></div></div>
    @if(!empty($legacyLinks))<div class="d-flex gap-2 flex-wrap mb-3">@foreach($legacyLinks as $link)<a class="btn btn-sm btn-outline-dark" href="{{ $link['route'] }}">{{ $link['label'] }}</a>@endforeach</div>@endif
    @if(!empty($planningCycles))
        <div class="row g-3 mb-3">
            <div class="col-xl-8">
                <div class="card shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center"><div><span class="fw-semibold">PMC Planning Cycle Control</span><div class="small text-muted">Move plans through review only after readiness evidence and blockers are clear.</div></div><span class="badge text-bg-warning">{{ $readinessBlockers ?? 0 }} readiness blockers</span></div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Plan</th><th scope="col">Scope</th><th scope="col">Status</th><th scope="col">Ready</th><th scope="col">Decision</th></tr></thead><tbody>
                        @forelse($planningCycles as $cycle)
                            <tr>
                                <td><div class="fw-semibold">{{ $cycle->title }}</div><div class="small text-muted">{{ str($cycle->cycle_type)->headline() }} | {{ $cycle->academic_year ?: 'year not set' }}</div></td>
                                <td>{{ $cycle->program?->code ?? 'All programs' }}<div class="small text-muted">{{ $cycle->term?->name ?? 'All terms' }}</div></td>
                                <td><span class="badge text-bg-secondary">{{ $cycle->status }}</span></td>
                                <td>{{ $cycle->readiness_score }}%</td>
                                <td><form method="POST" action="{{ route('academics.pmc.planning.cycles.update', $cycle) }}" class="d-flex gap-1">@csrf @method('PATCH')<select aria-label="Status" class="form-select form-select-sm" name="status"><option value="pmc_review">PMC review</option><option value="dean_review">Dean review</option><option value="approved">Approve</option><option value="published">Publish</option><option value="revision_requested">Revise</option><option value="closed">Close</option></select><input aria-label="Note" class="form-control form-control-sm" name="decision_note" placeholder="Note"><button class="btn btn-sm btn-outline-primary">Save cycle</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted small">No planning cycles yet. Create one to generate the readiness checklist.</td></tr>
                        @endforelse
                    </tbody></table></div>
                    <div class="card-footer py-2">{{ $planningCycles->links() }}</div>
                </div>
            </div>
            <div class="col-xl-4">
                <form method="POST" action="{{ route('academics.pmc.planning.store') }}" class="card shadow-sm h-100">@csrf
                    <div class="card-header py-2"><div class="fw-semibold">Create Planning Cycle</div><div class="small text-muted">Start a plan for annual, semester, term, elective, assessment, or resource readiness work.</div></div>
                    <div class="card-body vstack gap-2">
                        <input aria-label="Annual / semester / term plan title" class="form-control form-control-sm" name="title" placeholder="Annual / semester / term plan title" required>
                        <select aria-label="Cycle Type" class="form-select form-select-sm" name="cycle_type"><option value="annual_plan">Annual Plan</option><option value="semester_readiness">Semester Readiness</option><option value="program_term_plan">Program-Term Execution</option><option value="academic_calendar">Academic Calendar</option><option value="elective_plan">Elective Plan</option><option value="assessment_calendar">Assessment Calendar</option><option value="resource_readiness">Resource Readiness</option></select>
                        <input aria-label="Academic year" class="form-control form-control-sm" name="academic_year" placeholder="Academic year, e.g. 2026-27">
                        <select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">All programs</option>@foreach(($selectorOptions['programs'] ?? []) as $program)<option value="{{ $program->id }}">{{ $program->code }} - {{ $program->name }}</option>@endforeach</select>
                        <select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">All terms</option>@foreach(($selectorOptions['terms'] ?? []) as $term)<option value="{{ $term->id }}">{{ $term->name }}</option>@endforeach</select>
                        <select aria-label="Owner User" class="form-select form-select-sm" name="owner_user_id"><option value="">Current user</option>@foreach(($selectorOptions['users'] ?? []) as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
                        <div class="row g-1"><div class="col"><input aria-label="Starts At" type="date" class="form-control form-control-sm" name="starts_at"></div><div class="col"><input aria-label="Ends At" type="date" class="form-control form-control-sm" name="ends_at"></div></div>
                        <button class="btn btn-sm btn-primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if(!empty($readinessItems))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2"><div class="fw-semibold">Semester Readiness Checklist</div><div class="small text-muted">Blockers should become owned work items before publication or Dean escalation.</div></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Readiness Item</th><th scope="col">Owner</th><th scope="col">Status</th><th scope="col">Completion</th><th scope="col">Blocker Action</th></tr></thead><tbody>
                @foreach($readinessItems as $item)
                    <tr>
                        <td><div class="fw-semibold">{{ $item->title }}</div><div class="small text-muted">{{ str($item->section)->headline() }} | {{ $item->planningCycle?->title }}</div><div class="small">{{ $item->description }}</div></td>
                        <td>{{ $item->owner?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge text-bg-{{ $item->is_blocker ? 'danger' : 'secondary' }}">{{ $item->status }}</span><div class="small text-muted">{{ $item->severity }} | Due {{ optional($item->due_at)->format('d M') ?: 'not set' }}</div></td>
                        <td><form method="POST" action="{{ route('academics.pmc.semester-readiness.items.update', $item) }}" class="d-flex gap-1 align-items-center">@csrf @method('PATCH')<input aria-label="Completion Percent" type="number" class="form-control form-control-sm" name="completion_percent" value="{{ $item->completion_percent }}" min="0" max="100" style="max-width:76px"><select aria-label="Status" class="form-select form-select-sm" name="status"><option value="open" @selected($item->status==='open')>open</option><option value="in_progress" @selected($item->status==='in_progress')>in progress</option><option value="blocked" @selected($item->status==='blocked')>blocked</option><option value="done" @selected($item->status==='done')>done</option></select><input type="hidden" name="is_blocker" value="{{ $item->is_blocker ? 1 : 0 }}"><button class="btn btn-sm btn-outline-primary">Update readiness</button></form></td>
                        <td><form method="POST" action="{{ route('academics.pmc.semester-readiness.items.work-item', $item) }}">@csrf<button class="btn btn-sm btn-outline-danger">Create Action</button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $readinessItems->links() }}</div>
        </div>
    @endif
    @if(!empty($studentPlans))
        @if(!empty($studentSuccessEffectivenessDiagnostics))
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Student Success Intervention Effectiveness</div>
                        <div class="small text-muted">Overdue interventions, stale reviews, parent-call follow-through, evidence gaps, repeat-risk students, and closure effectiveness.</div>
                    </div>
                    <span class="badge text-bg-{{ ($studentSuccessEffectivenessDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $studentSuccessEffectivenessDiagnostics['status'] ?? 'attention_required') }}</span>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 text-center">
                        @foreach([
                            ['Risk Plans', $studentSuccessEffectivenessDiagnostics['risk_plans'] ?? 0],
                            ['Critical/High', $studentSuccessEffectivenessDiagnostics['critical_or_high_plans'] ?? 0],
                            ['Open Interventions', $studentSuccessEffectivenessDiagnostics['open_interventions'] ?? 0],
                            ['Overdue Interventions', $studentSuccessEffectivenessDiagnostics['overdue_interventions'] ?? 0],
                            ['Resolved', $studentSuccessEffectivenessDiagnostics['resolved_interventions'] ?? 0],
                            ['Effectiveness', ($studentSuccessEffectivenessDiagnostics['effectiveness_rate'] ?? 0) . '%'],
                            ['Stale Reviews', $studentSuccessEffectivenessDiagnostics['stale_plan_reviews'] ?? 0],
                            ['Parent Calls Due', $studentSuccessEffectivenessDiagnostics['parent_calls_due'] ?? 0],
                            ['Parent Overdue', $studentSuccessEffectivenessDiagnostics['parent_calls_overdue'] ?? 0],
                            ['Outcome Missing', $studentSuccessEffectivenessDiagnostics['parent_outcome_missing'] ?? 0],
                            ['Evidence Gaps', $studentSuccessEffectivenessDiagnostics['evidence_gaps'] ?? 0],
                            ['Repeat Risk Students', $studentSuccessEffectivenessDiagnostics['repeat_risk_students'] ?? 0],
                            ['Escalated', $studentSuccessEffectivenessDiagnostics['escalated_interventions'] ?? 0],
                            ['Blockers', $studentSuccessEffectivenessDiagnostics['blocker_total'] ?? 0],
                        ] as [$label, $value])
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted">{{ $label }}</div>
                                    <div class="fw-semibold">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer py-2 small">{{ $studentSuccessEffectivenessDiagnostics['recommended_action'] }}</div>
            </div>
        @endif
        <div class="row g-2 mb-3">
            @foreach(['Risk Plans'=>$studentSuccessSummary['plans'] ?? 0,'Critical Risk'=>$studentSuccessSummary['critical'] ?? 0,'Open Interventions'=>$studentSuccessSummary['interventions_open'] ?? 0,'Parent Calls Due'=>$studentSuccessSummary['parent_due'] ?? 0] as $label=>$value)
                <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>
            @endforeach
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center"><span class="fw-semibold">PMC Student Risk Command</span><form method="POST" action="{{ route('academics.pmc.student-success-v004.refresh') }}">@csrf<button class="btn btn-sm btn-outline-primary">Refresh Risk Signals</button></form></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Student</th><th scope="col">Risk Signals</th><th scope="col">Mentor</th><th scope="col">Next Action</th><th scope="col">Quick Commands</th></tr></thead><tbody>
                @foreach($studentPlans as $plan)
                    <tr>
                        <td><div class="fw-semibold">{{ $plan->student?->user?->name ?? 'Student' }}</div><div class="small text-muted">{{ $plan->program?->code ?? '-' }} | {{ $plan->student?->roll_number ?? $plan->student?->student_id }}</div></td>
                        <td><span class="badge text-bg-{{ $plan->risk_band === 'critical' ? 'danger' : ($plan->risk_band === 'high' ? 'warning' : 'secondary') }}">{{ $plan->risk_band }}</span><div class="small text-muted">Score {{ $plan->signals['risk_score'] ?? '-' }} | Attendance {{ $plan->signals['attendance_percent'] ?? '-' }}% | Marks {{ $plan->signals['average_marks'] ?? '-' }}</div><div class="small">{{ collect($plan->signals['reasons'] ?? [])->take(2)->join(', ') }}</div></td>
                        <td>{{ $plan->mentor?->name ?? 'Unassigned' }}<div class="small text-muted">{{ $plan->status }} | Review {{ optional($plan->next_review_at)->format('d M') ?: 'not set' }}</div></td>
                        <td class="small">{{ $plan->intervention_plan }}</td>
                        <td class="vstack gap-1">
                            <form method="POST" action="{{ route('academics.pmc.student-success-v004.interventions.store', $plan) }}" class="d-flex gap-1">@csrf<input type="hidden" name="intervention_type" value="mentor_meeting"><input type="hidden" name="reason" value="Created from PMC risk command"><button class="btn btn-sm btn-outline-primary">Mentor Action</button></form>
                            <form method="POST" action="{{ route('academics.pmc.student-success-v004.parent-escalations.store', $plan) }}" class="d-flex gap-1">@csrf<input type="hidden" name="reason" value="student_success_risk"><button class="btn btn-sm btn-outline-danger">Parent Call</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $studentPlans->links() }}</div>
        </div>
    @endif
    @if(!empty($studentInterventions))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Intervention Lifecycle</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Intervention</th><th scope="col">Owner</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
                @foreach($studentInterventions as $intervention)
                    <tr>
                        <td><div class="fw-semibold">{{ str($intervention->intervention_type)->headline() }} - {{ $intervention->student?->user?->name ?? 'Student' }}</div><div class="small text-muted">{{ $intervention->reason }} | Due {{ optional($intervention->due_at)->format('d M') ?: 'not set' }}</div><div class="small">{{ $intervention->action_plan }}</div></td>
                        <td>{{ $intervention->owner?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $intervention->status }}</span><div class="small text-muted">{{ $intervention->priority }}</div></td>
                        <td><form method="POST" action="{{ route('academics.pmc.interventions.update', $intervention) }}" class="d-flex gap-1">@csrf @method('PATCH')<select aria-label="Status" class="form-select form-select-sm" name="status"><option value="mentor_contacted">Mentor contacted</option><option value="parent_contacted">Parent contacted</option><option value="remedial_assigned">Remedial assigned</option><option value="under_review">Under review</option><option value="resolved">Resolved</option><option value="escalated">Escalated</option></select><input aria-label="Evidence note" class="form-control form-control-sm" name="evidence_note" placeholder="Evidence note"><button class="btn btn-sm btn-outline-primary">Update intervention</button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $studentInterventions->links() }}</div>
        </div>
    @endif
    @if(!empty($parentEscalations))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Parent / Guardian Escalations</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Student</th><th scope="col">Guardian</th><th scope="col">Reason</th><th scope="col">Status</th></tr></thead><tbody>
                @foreach($parentEscalations as $escalation)
                    <tr><td>{{ $escalation->student?->user?->name ?? 'Student' }}</td><td>{{ $escalation->guardian_name ?: 'Guardian' }}<div class="small text-muted">{{ $escalation->guardian_phone ?: 'phone not set' }}</div></td><td>{{ str($escalation->reason)->headline() }}</td><td>{{ $escalation->status }}<div class="small text-muted">{{ optional($escalation->scheduled_at)->format('d M Y H:i') ?: 'not scheduled' }}</div></td></tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $parentEscalations->links() }}</div>
        </div>
    @endif
    @if(!empty($deliveryCheckpoints))
        @if(!empty($deliveryExecutionDiagnostics))
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Course Delivery Execution Diagnostics</div>
                        <div class="small text-muted">Faculty session logs, topic completion, attendance/material updates, behind groups, overdue reviews, and remedial blockers.</div>
                    </div>
                    <span class="badge text-bg-{{ ($deliveryExecutionDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $deliveryExecutionDiagnostics['status'] ?? 'attention_required') }}</span>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 text-center">
                        @foreach([
                            ['Session Logs', $deliveryExecutionDiagnostics['session_logs'] ?? 0],
                            ['Pending Faculty Logs', $deliveryExecutionDiagnostics['pending_faculty_logs'] ?? 0],
                            ['Planned Logs', $deliveryExecutionDiagnostics['planned_logs'] ?? 0],
                            ['Missed', $deliveryExecutionDiagnostics['missed_logs'] ?? 0],
                            ['Cancelled', $deliveryExecutionDiagnostics['cancelled_logs'] ?? 0],
                            ['Rescheduled', $deliveryExecutionDiagnostics['rescheduled_logs'] ?? 0],
                            ['Attendance Pending', $deliveryExecutionDiagnostics['attendance_pending'] ?? 0],
                            ['Lesson Plan Pending', $deliveryExecutionDiagnostics['lesson_plan_pending'] ?? 0],
                            ['Material Pending', $deliveryExecutionDiagnostics['material_pending'] ?? 0],
                            ['Topic Planned Missing', $deliveryExecutionDiagnostics['topic_planned_missing'] ?? 0],
                            ['Topic Covered Missing', $deliveryExecutionDiagnostics['topic_covered_missing'] ?? 0],
                            ['Behind Groups', $deliveryExecutionDiagnostics['behind_groups'] ?? 0],
                            ['Overdue Reviews', $deliveryExecutionDiagnostics['overdue_reviews'] ?? 0],
                            ['Open Remedials', $deliveryExecutionDiagnostics['open_remedials'] ?? 0],
                            ['Blockers', $deliveryExecutionDiagnostics['blocker_total'] ?? 0],
                        ] as [$label, $value])
                            <div class="col-6 col-md-4 col-xl-2">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted">{{ $label }}</div>
                                    <div class="fw-semibold">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer py-2 small">{{ $deliveryExecutionDiagnostics['recommended_action'] }}</div>
            </div>
        @endif
        <div class="row g-2 mb-3">
            @foreach(['Delivery Checkpoints'=>$deliverySummary['checkpoints'] ?? 0,'Critical Delivery Risk'=>$deliverySummary['critical'] ?? 0,'Missed Sessions'=>$deliverySummary['missed_sessions'] ?? 0,'Open Remedials'=>$deliverySummary['open_remedials'] ?? 0] as $label=>$value)
                <div class="col-6 col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>
            @endforeach
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center"><span class="fw-semibold">PMC Course Delivery Checkpoints</span><form method="POST" action="{{ route('academics.pmc.course-delivery.refresh') }}">@csrf<button class="btn btn-sm btn-outline-primary">Refresh Delivery Signals</button></form></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Subject</th><th scope="col">Delivery</th><th scope="col">Risk Signals</th><th scope="col">Owner</th><th scope="col">Quick Action</th></tr></thead><tbody>
                @foreach($deliveryCheckpoints as $checkpoint)
                    <tr>
                        <td><div class="fw-semibold">{{ $checkpoint->subject?->name ?? 'Subject' }}</div><div class="small text-muted">{{ $checkpoint->subject?->code }} | {{ $checkpoint->subject?->program?->code ?? 'Program' }}</div></td>
                        <td><div class="small">Planned {{ $checkpoint->planned_sessions }} | Conducted {{ $checkpoint->conducted_sessions }} | Missed {{ $checkpoint->missed_sessions }}</div><div class="small text-muted">Attendance {{ $checkpoint->attendance_percent }}% | Marks pending {{ $checkpoint->marks_pending_count }} | Feedback {{ $checkpoint->feedback_score ?? '-' }}</div></td>
                        <td><span class="badge text-bg-{{ $checkpoint->risk_band === 'critical' ? 'danger' : ($checkpoint->risk_band === 'high' ? 'warning' : 'secondary') }}">{{ $checkpoint->risk_band }}</span><div class="small text-muted">Score {{ $checkpoint->delivery_score }} | Review {{ optional($checkpoint->next_review_at)->format('d M') ?: 'not set' }}</div><div class="small">{{ collect($checkpoint->signals['reasons'] ?? [])->take(2)->join(', ') }}</div></td>
                        <td>{{ $checkpoint->teacher?->user?->name ?? $checkpoint->owner?->name ?? 'Unassigned' }}</td>
                        <td><form method="POST" action="{{ route('academics.pmc.course-delivery.remedial-actions.store', $checkpoint) }}" class="d-flex gap-1">@csrf<input type="hidden" name="action_type" value="delivery_review"><input type="hidden" name="reason" value="Created from PMC delivery checkpoint"><button class="btn btn-sm btn-outline-danger">Create Remedial</button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $deliveryCheckpoints->links() }}</div>
        </div>
    @endif
    @if(!empty($groupDeliveryTrackers))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Section / Group Delivery Tracker</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Group</th><th scope="col">Faculty</th><th scope="col">Progress</th><th scope="col">Risk</th><th scope="col">Recommended Action</th></tr></thead><tbody>
                @foreach($groupDeliveryTrackers as $tracker)
                    <tr>
                        <td><div class="fw-semibold">{{ $tracker->courseGroup?->name ?? 'Course group' }}</div><div class="small text-muted">{{ str($tracker->courseGroup?->group_type ?? 'group')->headline() }} | {{ $tracker->subject?->code ?? $tracker->courseGroup?->subject?->code }}</div></td>
                        <td>{{ $tracker->teacher?->user?->name ?? $tracker->owner?->name ?? 'Unassigned' }}</td>
                        <td><div class="small">Progress {{ $tracker->delivery_progress }}% | Planned {{ $tracker->planned_sessions }} | Conducted {{ $tracker->conducted_sessions }}</div><div class="small text-muted">Missed {{ $tracker->missed_sessions }} | Pending logs {{ $tracker->pending_session_logs }} | Attendance {{ $tracker->attendance_percent ?? '-' }}%</div></td>
                        <td><span class="badge text-bg-{{ $tracker->risk_band === 'critical' ? 'danger' : ($tracker->risk_band === 'high' ? 'warning' : 'secondary') }}">{{ $tracker->risk_band }}</span><div class="small text-muted">Score {{ $tracker->risk_score }} | Review {{ optional($tracker->next_review_at)->format('d M') ?: 'not set' }}</div><div class="small">{{ collect($tracker->risk_reasons ?? [])->take(2)->join(', ') }}</div></td>
                        <td class="small">{{ collect($tracker->recommended_actions ?? [])->take(3)->join(', ') }}</td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $groupDeliveryTrackers->links() }}</div>
        </div>
    @endif
    @if(!empty($sessionDeliveryLogs))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Session Delivery Log Queue</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Session</th><th scope="col">Schedule</th><th scope="col">Status</th><th scope="col">Readiness</th></tr></thead><tbody>
                @foreach($sessionDeliveryLogs as $log)
                    <tr>
                        <td><div class="fw-semibold">{{ $log->courseGroup?->name ?? 'Course group' }}</div><div class="small text-muted">{{ $log->courseGroup?->subject?->name ?? $log->subject?->name ?? 'Subject' }} | {{ $log->teacher?->user?->name ?? 'Faculty' }}</div></td>
                        <td>{{ optional($log->scheduled_date)->format('d M Y') ?: 'date not set' }}<div class="small text-muted">Day {{ $log->day_of_week ?? '-' }} | {{ $log->slot?->name ?? 'slot not set' }} | {{ $log->classroom?->name ?? 'room not set' }}</div></td>
                        <td><span class="badge text-bg-{{ $log->session_status === 'missed' ? 'danger' : ($log->session_status === 'conducted' ? 'success' : 'secondary') }}">{{ $log->session_status }}</span><div class="small text-muted">{{ $log->gap_reason ?: 'No gap reason' }}</div></td>
                        <td class="small">Attendance {{ $log->attendance_marked ? 'marked' : 'pending' }} | Lesson plan {{ $log->lesson_plan_updated ? 'updated' : 'pending' }} | Material {{ $log->material_uploaded ? 'uploaded' : 'pending' }}</td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $sessionDeliveryLogs->links() }}</div>
        </div>
    @endif
    @if(!empty($remedialActions))
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold">Remedial Action Lifecycle</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Action</th><th scope="col">Owner</th><th scope="col">Status</th><th scope="col">Update</th></tr></thead><tbody>
                @foreach($remedialActions as $action)
                    <tr>
                        <td><div class="fw-semibold">{{ str($action->action_type)->headline() }} - {{ $action->subject?->name ?? $action->checkpoint?->subject?->name ?? 'Subject' }}</div><div class="small text-muted">{{ $action->reason }} | Due {{ optional($action->due_at)->format('d M') ?: 'not set' }}</div><div class="small">{{ $action->action_plan }}</div></td>
                        <td>{{ $action->owner?->name ?? $action->teacher?->user?->name ?? 'Unassigned' }}</td>
                        <td><span class="badge text-bg-secondary">{{ $action->status }}</span><div class="small text-muted">{{ $action->priority }}</div></td>
                        <td><form method="POST" action="{{ route('academics.pmc.remedial-planning.actions.update', $action) }}" class="d-flex gap-1">@csrf @method('PATCH')<select aria-label="Status" class="form-select form-select-sm" name="status"><option value="faculty_contacted">Faculty contacted</option><option value="makeup_scheduled">Makeup scheduled</option><option value="marks_collected">Marks collected</option><option value="under_review">Under review</option><option value="resolved">Resolved</option><option value="escalated">Escalated</option></select><input aria-label="Evidence note" class="form-control form-control-sm" name="evidence_note" placeholder="Evidence note"><button class="btn btn-sm btn-outline-primary">Update remedial action</button></form></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="card-footer py-2">{{ $remedialActions->links() }}</div>
        </div>
    @endif
    <div class="row g-3"><div class="col-xl-8"><div class="card shadow-sm"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Record</th><th scope="col">Owner</th><th scope="col">Status</th><th scope="col">Risk</th><th scope="col">Score</th><th scope="col">Action</th></tr></thead><tbody>@forelse($records as $record)<tr><td><div class="fw-semibold">{{ $record->title }}</div><div class="small text-muted">{{ str($record->record_type)->headline() }} | {{ $record->program?->code ?? 'All programs' }} | Due {{ optional($record->due_at)->format('d M Y') ?: 'not set' }}</div></td><td>{{ $record->owner?->name ?? 'Unassigned' }}</td><td>{{ $record->status }}</td><td>{{ $record->risk_band ?? 'normal' }}</td><td>{{ $record->score }}%</td><td><form method="POST" action="{{ route('academics.pmc.v004.records.work-item', $record) }}">@csrf<button class="btn btn-sm btn-outline-primary">Create Action</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No PMC records match the current filters.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $records->links() }}</div></div></div>
    <div class="col-xl-4"><form method="POST" action="{{ route('academics.pmc.v004.records.store') }}" class="card shadow-sm">@csrf<input type="hidden" name="record_type" value="{{ $records->first()?->record_type ?? 'planning' }}"><div class="card-header py-2 fw-semibold">Create PMC Record</div><div class="card-body vstack gap-2"><input aria-label="Title" class="form-control form-control-sm" name="title" placeholder="Title" required><input aria-label="Category" class="form-control form-control-sm" name="category" placeholder="Category"><textarea aria-label="Description" class="form-control form-control-sm" name="description" placeholder="Description"></textarea><select aria-label="Risk Band" class="form-select form-select-sm" name="risk_band"><option value="medium">medium</option><option value="high">high</option><option value="critical">critical</option><option value="low">low</option></select><input aria-label="Due At" type="date" class="form-control form-control-sm" name="due_at"><button class="btn btn-sm btn-primary">Create</button></div></form></div></div>
</div>
@endsection
