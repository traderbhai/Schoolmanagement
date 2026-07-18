@extends('layouts.admin')
@section('title', $title)
@section('content')
@php($selectorOptions = $selectorOptions ?? [])
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div><h1 class="h4 mb-1">{{ $title }}</h1><div class="small text-muted">{{ $description }}</div></div>
        @include('academics.pmc.v041.partials.nav')
    </div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">PMC timetable source workflow</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Filter program/batch/term</span>
            <span class="badge text-bg-light border">2. Resolve diagnostics</span>
            <span class="badge text-bg-light border">3. Update source records</span>
            <span class="badge text-bg-light border">4. Recheck readiness</span>
            <span class="badge text-bg-light border">5. Export or continue to generator</span>
        </div>
        <div class="text-muted mt-2">These pages are the source lists behind the timetable dashboard. Fix records here before generating, publishing, freezing, or notifying students and faculty.</div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label small">Search</label><input aria-label="Student, group, subject, faculty" class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Student, group, subject, faculty"></div>
                <div class="col-md-2"><label class="form-label small">Program</label><select aria-label="Program" class="form-select form-select-sm" name="program_id"><option value="">All programs</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Batch</label><select aria-label="Batch" class="form-select form-select-sm" name="batch_id"><option value="">All batches</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}" @selected((string) request('batch_id') === (string) $batch->id)>{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Term</label><select aria-label="Term" class="form-select form-select-sm" name="term_id"><option value="">All terms</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}" @selected((string) request('term_id') === (string) $term->id)>{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Subject</label><select aria-label="Subject" class="form-select form-select-sm" name="subject_id"><option value="">All subjects</option>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}" @selected((string) request('subject_id') === (string) $subject->id)>{{ $subject->code ?: $subject->name }} - {{ $subject->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small">Status</label><input aria-label="Status" class="form-control form-control-sm" name="status" value="{{ request('status') }}"></div>
                <div class="col-md-2"><label class="form-label small">Sort</label><select aria-label="Sort" class="form-select form-select-sm" name="sort"><option value="">Default</option><option value="day_of_week" @selected(request('sort')==='day_of_week')>Day</option><option value="timetable_slot_id" @selected(request('sort')==='timetable_slot_id')>Slot</option><option value="confidence" @selected(request('sort')==='confidence')>Confidence</option><option value="status" @selected(request('sort')==='status')>Status</option></select></div>
                <div class="col-md-2"><label class="form-label small">Order</label><select aria-label="Direction" class="form-select form-select-sm" name="direction"><option value="asc" @selected(request('direction','asc')==='asc')>Asc</option><option value="desc" @selected(request('direction')==='desc')>Desc</option></select></div>
                <div class="col-md-4 d-flex gap-1"><button type="submit" class="btn btn-sm btn-primary">Apply source filters</button><a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Clear source filters</a><a href="{{ route('academics.pmc.v041.surface.export', ['surface' => $surface] + request()->query()) }}" class="btn btn-sm btn-outline-success">Export current source view</a></div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All current records' }}</div>
        </div>
    </div>

    @isset($allocationPressureDiagnostics)
        @include('academics.pmc.v041.partials.diagnostic-card', [
            'title' => 'Allocation Pressure Diagnostics',
            'subtitle' => 'Choice-window pressure, waitlists, add/drop exceptions, repeat/backlog cases, duplicate baskets, and incomplete student baskets before section locking.',
            'status' => $allocationPressureDiagnostics['status'] ?? 'attention_required',
            'metricColumnClass' => 'col-6 col-md-4 col-xl',
            'metrics' => [
                ['Choices', $allocationPressureDiagnostics['elective_choices_total'] ?? 0],
                ['Submitted', $allocationPressureDiagnostics['submitted_choices'] ?? 0],
                ['Allocated', $allocationPressureDiagnostics['allocated_choices'] ?? 0],
                ['Waitlisted', $allocationPressureDiagnostics['waitlisted_choices'] ?? 0],
                ['Choice Students Pending', $allocationPressureDiagnostics['unprocessed_choice_students'] ?? 0],
                ['Waitlist Subjects', $allocationPressureDiagnostics['waitlist_subjects'] ?? 0],
                ['Add/Drop Pending', $allocationPressureDiagnostics['pending_add_drop'] ?? 0],
                ['Repeat/Backlog Pending', $allocationPressureDiagnostics['pending_repeat_backlog'] ?? 0],
                ['Dean Pending', $allocationPressureDiagnostics['dean_approval_pending'] ?? 0],
                ['Duplicate Baskets', $allocationPressureDiagnostics['duplicate_student_subject_allocations'] ?? 0],
                ['No Basket', $allocationPressureDiagnostics['students_without_basket'] ?? 0],
                ['Single-Course Baskets', $allocationPressureDiagnostics['single_course_baskets'] ?? 0],
                ['Manual Overrides', $allocationPressureDiagnostics['manual_overrides'] ?? 0],
                ['Recent Rounds', $allocationPressureDiagnostics['recent_allocation_rounds'] ?? 0],
                ['Pressure', $allocationPressureDiagnostics['pressure_total'] ?? 0],
            ],
            'recommendedAction' => $allocationPressureDiagnostics['recommended_action'] ?? 'Review allocation pressure before section locking.',
            'sourceUrl' => route('academics.pmc.elective-allocation.index'),
            'sourceLabel' => 'Open allocation pressure source list',
        ])
    @endisset

    @if(isset($batches))
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.batches')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.allocation')</div>
        </div>
        @isset($electiveChoices)
            <div class="card shadow-sm mt-3">
                <div class="card-header py-2 fw-semibold">Elective Choices</div>
                <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Student</th><th scope="col">Subject</th><th scope="col">Rank</th><th scope="col">Score</th><th scope="col">Status</th></tr></thead><tbody>
                    @forelse($electiveChoices as $choice)<tr><td>{{ $choice->student?->user?->name ?? $choice->student?->enrollment_number ?? $choice->student?->roll_number ?? $choice->student?->student_id ?? 'Unassigned student' }}</td><td>{{ $choice->subject?->name ?? $choice->subject?->code ?? 'Unassigned subject' }}</td><td>{{ $choice->preference_rank }}</td><td>{{ $choice->priority_score }}</td><td>{{ $choice->status }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No elective choice-window records.</td></tr>@endforelse
                </tbody></table></div><div class="card-footer py-2">{{ $electiveChoices->links() }}</div>
            </div>
        @endisset
        @includeWhen(isset($allocationExceptions), 'academics.pmc.v041.tables.course-allocation-exceptions')
    @elseif(isset($allocations))
        @isset($basketDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-card', [
                'title' => 'Course Basket Diagnostics',
                'subtitle' => 'Readiness signals that must be cleared before section/group locking and timetable generation.',
                'status' => $basketDiagnostics['status'],
                'metrics' => [
                    ['Total', $basketDiagnostics['total_allocations']],
                    ['Ready', $basketDiagnostics['ready_allocations']],
                    ['Ungrouped', $basketDiagnostics['ungrouped_allocations']],
                    ['Waitlisted', $basketDiagnostics['waitlisted_allocations']],
                    ['Pending Exceptions', $basketDiagnostics['pending_exceptions']],
                    ['Credit Overload', $basketDiagnostics['credit_overload_baskets']],
                    ['Flagged', $basketDiagnostics['flagged_allocations']],
                ],
                'recommendedAction' => $basketDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.student-course-baskets.index'),
                'sourceLabel' => 'Open basket source list',
            ])
        @endisset
        <div class="card shadow-sm">
            <div class="card-header py-2 fw-semibold">Student Course Baskets</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                <thead><tr><th scope="col">Student</th><th scope="col">Subject</th><th scope="col">Type</th><th scope="col">Approval</th><th scope="col">Basket</th><th scope="col">Flags</th></tr></thead>
                <tbody>@forelse($allocations as $allocation)<tr>
                    <td>{{ $allocation->student?->user?->name ?? $allocation->student?->enrollment_number ?? $allocation->student?->roll_number ?? $allocation->student?->student_id ?? 'Unassigned student' }}</td>
                    <td>{{ $allocation->subject?->name ?? $allocation->subject?->code ?? 'Unassigned subject' }}</td>
                    <td>{{ $allocation->allocation_type }}</td>
                    <td>{{ $allocation->approval_status }}</td>
                    <td>{{ $allocation->basket_status }}</td>
                    <td>{{ collect($allocation->validation_flags ?? [])->keys()->implode(', ') ?: 'clear' }}</td>
                </tr>@empty<tr><td colspan="6" class="text-muted">No student course basket records.</td></tr>@endforelse</tbody>
            </table></div><div class="card-footer py-2">{{ $allocations->links() }}</div>
        </div>
        @includeWhen(isset($allocationExceptions), 'academics.pmc.v041.tables.course-allocation-exceptions')
    @elseif(isset($groups))
        @isset($groupDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-card', [
                'title' => 'Section And Group Diagnostics',
                'subtitle' => 'Resolve capacity, lock, membership, faculty, and adjustment blockers before timetable generation.',
                'status' => $groupDiagnostics['status'],
                'metrics' => [
                    ['Total', $groupDiagnostics['total_groups']],
                    ['Ready', $groupDiagnostics['ready_groups']],
                    ['Unlocked', $groupDiagnostics['unlocked_groups']],
                    ['Under Min', $groupDiagnostics['under_min_groups']],
                    ['Over Capacity', $groupDiagnostics['over_capacity_groups']],
                    ['No Faculty', $groupDiagnostics['groups_without_faculty']],
                    ['Ungrouped Allocations', $groupDiagnostics['ungrouped_allocations']],
                    ['Pending Adjustments', $groupDiagnostics['pending_adjustments']],
                    ['Strength Mismatch', $groupDiagnostics['strength_mismatch_groups']],
                ],
                'recommendedAction' => $groupDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.course-groups.index'),
                'sourceLabel' => 'Open group source list',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.groups')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.group')</div>
        </div>
        @includeWhen(isset($groupAdjustments), 'academics.pmc.v041.tables.course-group-adjustments')
    @elseif(isset($assignments))
        @isset($facultyDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-card', [
                'title' => 'Faculty Allocation Diagnostics',
                'subtitle' => 'Resolve exact faculty, acknowledgement, preference, backup, and load-review blockers before generation.',
                'status' => $facultyDiagnostics['status'],
                'metrics' => [
                    ['Assignments', $facultyDiagnostics['total_assignments']],
                    ['Ready', $facultyDiagnostics['ready_assignments']],
                    ['Groups Assigned', $facultyDiagnostics['assigned_groups']],
                    ['Missing Primary', $facultyDiagnostics['groups_missing_primary']],
                    ['No Backup', $facultyDiagnostics['groups_without_backup']],
                    ['Pending Ack', $facultyDiagnostics['pending_acknowledgements']],
                    ['No Ack Request', $facultyDiagnostics['assignments_without_acknowledgement']],
                    ['Missing Preference', $facultyDiagnostics['teachers_missing_preference']],
                    ['Load Blockers', $facultyDiagnostics['load_review_blockers']],
                    ['Overload', $facultyDiagnostics['overload_reviews']],
                ],
                'recommendedAction' => $facultyDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.section-faculty-allocation.index'),
                'sourceLabel' => 'Open faculty allocation source list',
            ])
        @endisset
        @isset($facultySuitabilityDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-card', [
                'title' => 'Faculty Suitability Diagnostics',
                'subtitle' => 'Subject expertise, adjunct availability, acknowledgement concerns, overload approvals, and backup-only primary gaps before timetable generation.',
                'status' => $facultySuitabilityDiagnostics['status'] ?? 'attention_required',
                'metricColumnClass' => 'col-6 col-md-4 col-xl',
                'metrics' => [
                    ['Assignments', $facultySuitabilityDiagnostics['total_assignments'] ?? 0],
                    ['Suitable', $facultySuitabilityDiagnostics['suitable_assignments'] ?? 0],
                    ['Expertise Gaps', $facultySuitabilityDiagnostics['missing_expertise'] ?? 0],
                    ['Adjunct Day Risk', $facultySuitabilityDiagnostics['adjunct_day_risk'] ?? 0],
                    ['Restrictions', $facultySuitabilityDiagnostics['restriction_risks'] ?? 0],
                    ['Ack Concerns', $facultySuitabilityDiagnostics['acknowledgement_concerns'] ?? 0],
                    ['Declined', $facultySuitabilityDiagnostics['declined_assignments'] ?? 0],
                    ['Overload Risk', $facultySuitabilityDiagnostics['overload_risks'] ?? 0],
                    ['Unapproved', $facultySuitabilityDiagnostics['unapproved_suitability'] ?? 0],
                    ['Backup-Only Gap', $facultySuitabilityDiagnostics['backup_only_primary_gap'] ?? 0],
                    ['Blockers', $facultySuitabilityDiagnostics['blocker_total'] ?? 0],
                ],
                'recommendedAction' => $facultySuitabilityDiagnostics['recommended_action'] ?? 'Review faculty suitability before timetable generation.',
                'sourceUrl' => route('academics.pmc.section-faculty-allocation.index'),
                'sourceLabel' => 'Open suitability source list',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.faculty')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.faculty')</div>
        </div>
    @elseif(isset($lockedSlots))
        @isset($readinessInputDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-card', [
                'title' => 'Readiness Input Diagnostics',
                'subtitle' => 'Resolve faculty preference, locked-slot, hard-lock collision, and room/lab blockers before timetable generation.',
                'status' => $readinessInputDiagnostics['status'],
                'metrics' => [
                    ['Preferences', $readinessInputDiagnostics['total_preferences']],
                    ['Complete Pref', $readinessInputDiagnostics['complete_preferences']],
                    ['Incomplete Pref', $readinessInputDiagnostics['incomplete_preferences']],
                    ['Restrictive Pref', $readinessInputDiagnostics['restrictive_preferences']],
                    ['Active Locks', $readinessInputDiagnostics['active_locked_slots']],
                    ['Hard Locks', $readinessInputDiagnostics['hard_locked_slots']],
                    ['Soft Locks', $readinessInputDiagnostics['soft_locked_slots']],
                    ['Missing Context', $readinessInputDiagnostics['locked_slots_missing_context']],
                    ['Lock Collisions', $readinessInputDiagnostics['hard_lock_collisions']],
                    ['Room Blockers', $readinessInputDiagnostics['room_review_blockers']],
                    ['Lab Not Ready', $readinessInputDiagnostics['lab_not_ready']],
                    ['Capacity Exceptions', $readinessInputDiagnostics['capacity_exceptions']],
                ],
                'recommendedAction' => $readinessInputDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.locked-slots.index'),
                'sourceLabel' => 'Open readiness source list',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.locked-slots')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.locked-slot')</div>
        </div>
    @elseif(isset($runs))
        @isset($generationDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-grid-card', [
                'title' => 'Generation Validation Diagnostics',
                'subtitle' => 'Latest run freshness, unscheduled classes, conflicts, resolution actions, publish checks, and impact-preview readiness.',
                'status' => $generationDiagnostics['status'] ?? 'attention_required',
                'metrics' => [
                    ['Run', $generationDiagnostics['latest_run_title'] ?? 'No run'],
                    ['Status', str_replace('_', ' ', $generationDiagnostics['latest_run_status'] ?? 'missing')],
                    ['Scheduled', $generationDiagnostics['scheduled_classes'] ?? 0],
                    ['Unscheduled', $generationDiagnostics['unscheduled_classes'] ?? 0],
                    ['Hard Conflicts', $generationDiagnostics['hard_conflicts'] ?? 0],
                    ['Soft Warnings', $generationDiagnostics['soft_warnings'] ?? 0],
                    ['Quality', ($generationDiagnostics['quality_score'] ?? 0) . '%'],
                    ['Solver Attempts', $generationDiagnostics['solver_attempts'] ?? 0],
                    ['Failed Attempts', $generationDiagnostics['failed_solver_attempts'] ?? 0],
                    ['Open Actions', $generationDiagnostics['open_resolution_actions'] ?? 0],
                    ['Publish Blocks', $generationDiagnostics['blocking_publish_checks'] ?? 0],
                    ['Impact Rows', $generationDiagnostics['impact_preview_records'] ?? 0],
                    ['Missing Impact', $generationDiagnostics['missing_impact_preview'] ?? 0],
                    ['Stale Inputs', $generationDiagnostics['stale_input_sources'] ?? 0],
                    ['Blockers', $generationDiagnostics['blocker_total'] ?? 0],
                ],
                'recommendedAction' => $generationDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.timetable-generator.index'),
                'sourceLabel' => 'Open generator source',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.generator')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.generator')</div>
        </div>
    @elseif(isset($items))
        @include('academics.pmc.v041.tables.planner')
    @elseif(isset($versions))
        @isset($publishReadinessDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-grid-card', [
                'title' => 'Publish And Freeze Readiness',
                'subtitle' => 'Official go-live status across version lifecycle, revision requests, publish checks, notifications, sync, and impact.',
                'status' => $publishReadinessDiagnostics['status'] ?? 'attention_required',
                'metrics' => [
                    ['Latest Version', $publishReadinessDiagnostics['latest_version_label'] ?? 'No version'],
                    ['Version Status', str_replace('_', ' ', $publishReadinessDiagnostics['latest_version_status'] ?? 'missing')],
                    ['Lifecycle', str_replace('_', ' ', $publishReadinessDiagnostics['latest_lifecycle_status'] ?? 'missing')],
                    ['Approval', str_replace('_', ' ', $publishReadinessDiagnostics['latest_approval_status'] ?? 'missing')],
                    ['Published', $publishReadinessDiagnostics['published_versions'] ?? 0],
                    ['Frozen', $publishReadinessDiagnostics['frozen_versions'] ?? 0],
                    ['Missing Official', $publishReadinessDiagnostics['missing_official_version'] ?? 0],
                    ['Missing Workflow', $publishReadinessDiagnostics['missing_lifecycle_workflow'] ?? 0],
                    ['Publish Blocks', $publishReadinessDiagnostics['blocking_publish_checks'] ?? 0],
                    ['Change Requests', $publishReadinessDiagnostics['pending_change_requests'] ?? 0],
                    ['Failed Notices', $publishReadinessDiagnostics['failed_notifications'] ?? 0],
                    ['Queued Notices', $publishReadinessDiagnostics['queued_notifications'] ?? 0],
                    ['Synced Entries', $publishReadinessDiagnostics['operational_entries_synced'] ?? 0],
                    ['Impact Rows', $publishReadinessDiagnostics['impact_records'] ?? 0],
                    ['Blockers', $publishReadinessDiagnostics['blocker_total'] ?? 0],
                ],
                'recommendedAction' => $publishReadinessDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.timetable-versions-v041.index'),
                'sourceLabel' => 'Open version lifecycle board',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.versions')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.change-request')</div>
        </div>
    @elseif(isset($recommendations))
        @isset($substitutionEmergencyDiagnostics)
            @include('academics.pmc.v041.partials.diagnostic-grid-card', [
                'title' => 'Substitution Emergency Desk',
                'subtitle' => 'Today/tomorrow uncovered classes, weak substitute recommendations, same-day changes, repeated substitution risk, and notification readiness.',
                'status' => $substitutionEmergencyDiagnostics['status'] ?? 'attention_required',
                'metrics' => [
                    ['Today', $substitutionEmergencyDiagnostics['today_recommendations'] ?? 0],
                    ['Upcoming', $substitutionEmergencyDiagnostics['upcoming_recommendations'] ?? 0],
                    ['Uncovered Today', $substitutionEmergencyDiagnostics['uncovered_today'] ?? 0],
                    ['Pending', $substitutionEmergencyDiagnostics['pending_recommendations'] ?? 0],
                    ['Low Score', $substitutionEmergencyDiagnostics['low_score_recommendations'] ?? 0],
                    ['Failed Notices', $substitutionEmergencyDiagnostics['failed_substitution_notices'] ?? 0],
                    ['Queued Notices', $substitutionEmergencyDiagnostics['queued_substitution_notices'] ?? 0],
                    ['Same-Day Changes', $substitutionEmergencyDiagnostics['same_day_change_requests'] ?? 0],
                    ['Repeat Faculty', $substitutionEmergencyDiagnostics['repeated_original_teachers'] ?? 0],
                    ['Repeat Groups', $substitutionEmergencyDiagnostics['repeated_course_groups'] ?? 0],
                    ['Blockers', $substitutionEmergencyDiagnostics['blocker_total'] ?? 0],
                ],
                'recommendedAction' => $substitutionEmergencyDiagnostics['recommended_action'],
                'sourceUrl' => route('academics.pmc.substitution-intelligence.index'),
                'sourceLabel' => 'Open substitution desk',
            ])
        @endisset
        <div class="row g-3">
            <div class="col-xl-8">@include('academics.pmc.v041.tables.substitutions')</div>
            <div class="col-xl-4">@include('academics.pmc.v041.forms.substitution')</div>
        </div>
    @else
        @include('academics.pmc.v041.tables.reports')
    @endif
</div>
@endsection
