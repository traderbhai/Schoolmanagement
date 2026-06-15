@extends('layouts.admin')
@section('title', 'PMC Timetable OS')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">PMC Timetable OS</h1>
            <div class="small text-muted">Student-course allocation, sections/groups, faculty load, locked slots, constraint generation, approval/freeze, substitution, and reports. Scope: {{ $scopeLabel }}</div>
        </div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Allocation Batches', $kpis['allocation_batches'], route('academics.pmc.course-allocation.index')],
            ['Student Allocations', $kpis['student_allocations'], route('academics.pmc.student-course-baskets.index')],
            ['Course Groups', $kpis['course_groups'], route('academics.pmc.course-groups.index')],
            ['Faculty Assignments', $kpis['faculty_assignments'], route('academics.pmc.section-faculty-allocation.index')],
            ['Locked Slots', $kpis['locked_slots'], route('academics.pmc.locked-slots.index')],
            ['Hard Conflicts', $kpis['hard_conflicts'], route('academics.pmc.timetable-planner.index', ['severity' => 'hard'])],
            ['Soft Warnings', $kpis['soft_warnings'], route('academics.pmc.timetable-quality.index', ['severity' => 'soft'])],
            ['Quality Score', $kpis['quality_score'] . '%', route('academics.pmc.timetable-quality.index')],
        ] as [$label, $value, $url])
            <div class="col-6 col-md-3 col-xl">
                <a href="{{ $url }}" class="card h-100 shadow-sm text-decoration-none">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="h4 mb-0">{{ $value }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Semester Launch Control</div>
                <div class="small text-muted">Ordered PMC launch sequence from student course baskets to publish notifications.</div>
            </div>
            <div class="text-end">
                <span class="badge text-bg-{{ ($launchControl['status'] ?? '') === 'ready_to_launch' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $launchControl['status'] ?? 'attention_required') }}</span>
                <div class="small text-muted">{{ $launchControl['ready_stages'] ?? 0 }}/{{ $launchControl['total_stages'] ?? 0 }} ready | {{ $launchControl['blocked_stages'] ?? 0 }} blocked</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Stage</th><th>Done</th><th>Blockers</th><th>Recommended Action</th><th>Source</th></tr></thead>
                <tbody>
                    @foreach($launchControl['stages'] ?? [] as $stage)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $stage['label'] }}</div>
                                <span class="badge text-bg-{{ $stage['status'] === 'ready' ? 'success' : 'warning' }}">{{ $stage['status'] }}</span>
                            </td>
                            <td>{{ $stage['done_count'] }}</td>
                            <td>{{ $stage['blocker_count'] }}</td>
                            <td class="small">{{ $stage['recommended_action'] }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route($stage['route'], $stage['filters'] ?? []) }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(!empty($launchControl['next_action']))
            <div class="card-footer py-2 small">
                <span class="fw-semibold">Next PMC action:</span>
                {{ $launchControl['next_action']['recommended_action'] }}
                <a class="ms-2" href="{{ route($launchControl['next_action']['route'], $launchControl['next_action']['filters'] ?? []) }}">Open source list</a>
            </div>
        @endif
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Course Basket Diagnostics</div>
                <div class="small text-muted">Academic-rule readiness before sections/groups and timetable generation.</div>
            </div>
            <span class="badge text-bg-{{ ($basketDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $basketDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
                ['Total', $basketDiagnostics['total_allocations'] ?? 0],
                ['Ready', $basketDiagnostics['ready_allocations'] ?? 0],
                ['Ungrouped', $basketDiagnostics['ungrouped_allocations'] ?? 0],
                ['Waitlisted', $basketDiagnostics['waitlisted_allocations'] ?? 0],
                ['Pending Exceptions', $basketDiagnostics['pending_exceptions'] ?? 0],
                ['Credit Overload', $basketDiagnostics['credit_overload_baskets'] ?? 0],
                ['Flagged', $basketDiagnostics['flagged_allocations'] ?? 0],
            ] as [$label, $value])
                <div class="col-6 col-md border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $basketDiagnostics['recommended_action'] ?? 'Review basket readiness.' }}</span>
            <a href="{{ route('academics.pmc.student-course-baskets.index') }}">Open basket source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Allocation Pressure Diagnostics</div>
                <div class="small text-muted">Choice-window pressure, waitlists, add/drop exceptions, repeat/backlog cases, duplicate baskets, and incomplete student baskets before section locking.</div>
            </div>
            <span class="badge text-bg-{{ ($allocationPressureDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $allocationPressureDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
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
            ] as [$label, $value])
                <div class="col-6 col-md-4 col-xl border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $allocationPressureDiagnostics['recommended_action'] ?? 'Review allocation pressure before section locking.' }}</span>
            <a href="{{ route('academics.pmc.elective-allocation.index') }}">Open allocation pressure source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Section And Group Diagnostics</div>
                <div class="small text-muted">Capacity, lock, membership, faculty, and adjustment readiness before timetable generation.</div>
            </div>
            <span class="badge text-bg-{{ ($groupDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $groupDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
                ['Total', $groupDiagnostics['total_groups'] ?? 0],
                ['Ready', $groupDiagnostics['ready_groups'] ?? 0],
                ['Unlocked', $groupDiagnostics['unlocked_groups'] ?? 0],
                ['Under Min', $groupDiagnostics['under_min_groups'] ?? 0],
                ['Over Capacity', $groupDiagnostics['over_capacity_groups'] ?? 0],
                ['No Faculty', $groupDiagnostics['groups_without_faculty'] ?? 0],
                ['Ungrouped Allocations', $groupDiagnostics['ungrouped_allocations'] ?? 0],
                ['Pending Adjustments', $groupDiagnostics['pending_adjustments'] ?? 0],
                ['Strength Mismatch', $groupDiagnostics['strength_mismatch_groups'] ?? 0],
            ] as [$label, $value])
                <div class="col-6 col-md border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $groupDiagnostics['recommended_action'] ?? 'Review section and group readiness.' }}</span>
            <a href="{{ route('academics.pmc.course-groups.index') }}">Open group source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Faculty Allocation Diagnostics</div>
                <div class="small text-muted">Exact group faculty, acknowledgements, preferences, backup coverage, and load-review readiness.</div>
            </div>
            <span class="badge text-bg-{{ ($facultyDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $facultyDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
                ['Assignments', $facultyDiagnostics['total_assignments'] ?? 0],
                ['Ready', $facultyDiagnostics['ready_assignments'] ?? 0],
                ['Groups Assigned', $facultyDiagnostics['assigned_groups'] ?? 0],
                ['Missing Primary', $facultyDiagnostics['groups_missing_primary'] ?? 0],
                ['No Backup', $facultyDiagnostics['groups_without_backup'] ?? 0],
                ['Pending Ack', $facultyDiagnostics['pending_acknowledgements'] ?? 0],
                ['No Ack Request', $facultyDiagnostics['assignments_without_acknowledgement'] ?? 0],
                ['Missing Preference', $facultyDiagnostics['teachers_missing_preference'] ?? 0],
                ['Load Blockers', $facultyDiagnostics['load_review_blockers'] ?? 0],
                ['Overload', $facultyDiagnostics['overload_reviews'] ?? 0],
            ] as [$label, $value])
                <div class="col-6 col-md border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $facultyDiagnostics['recommended_action'] ?? 'Review faculty allocation readiness.' }}</span>
            <a href="{{ route('academics.pmc.section-faculty-allocation.index') }}">Open faculty allocation source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Faculty Suitability Diagnostics</div>
                <div class="small text-muted">Subject expertise, adjunct availability, acknowledgement concerns, overload approvals, and backup-only primary gaps before timetable generation.</div>
            </div>
            <span class="badge text-bg-{{ ($facultySuitabilityDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $facultySuitabilityDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
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
            ] as [$label, $value])
                <div class="col-6 col-md-4 col-xl border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $facultySuitabilityDiagnostics['recommended_action'] ?? 'Review faculty suitability before timetable generation.' }}</span>
            <a href="{{ route('academics.pmc.section-faculty-allocation.index') }}">Open suitability source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Readiness Input Diagnostics</div>
                <div class="small text-muted">Faculty availability, locked/manual slots, hard-lock collisions, and room/lab readiness before generation.</div>
            </div>
            <span class="badge text-bg-{{ ($readinessInputDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $readinessInputDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="row g-0 text-center">
            @foreach([
                ['Preferences', $readinessInputDiagnostics['total_preferences'] ?? 0],
                ['Incomplete Pref', $readinessInputDiagnostics['incomplete_preferences'] ?? 0],
                ['Restrictive Pref', $readinessInputDiagnostics['restrictive_preferences'] ?? 0],
                ['Active Locks', $readinessInputDiagnostics['active_locked_slots'] ?? 0],
                ['Hard Locks', $readinessInputDiagnostics['hard_locked_slots'] ?? 0],
                ['Missing Context', $readinessInputDiagnostics['locked_slots_missing_context'] ?? 0],
                ['Lock Collisions', $readinessInputDiagnostics['hard_lock_collisions'] ?? 0],
                ['Room Blockers', $readinessInputDiagnostics['room_review_blockers'] ?? 0],
                ['Lab Not Ready', $readinessInputDiagnostics['lab_not_ready'] ?? 0],
                ['Capacity Exceptions', $readinessInputDiagnostics['capacity_exceptions'] ?? 0],
            ] as [$label, $value])
                <div class="col-6 col-md border-top border-end py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $readinessInputDiagnostics['recommended_action'] ?? 'Review readiness inputs.' }}</span>
            <a href="{{ route('academics.pmc.locked-slots.index') }}">Open readiness source list</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Generation Validation Diagnostics</div>
                <div class="small text-muted">Latest solver run, validation blockers, impact preview, and publish-check readiness.</div>
            </div>
            <span class="badge text-bg-{{ ($generationDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $generationDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="card-body py-2">
            <div class="row g-2 text-center">
                @foreach([
                    ['Run', $generationDiagnostics['latest_run_title'] ?? 'No run'],
                    ['Status', str_replace('_', ' ', $generationDiagnostics['latest_run_status'] ?? 'missing')],
                    ['Scheduled', $generationDiagnostics['scheduled_classes'] ?? 0],
                    ['Unscheduled', $generationDiagnostics['unscheduled_classes'] ?? 0],
                    ['Hard Conflicts', $generationDiagnostics['hard_conflicts'] ?? 0],
                    ['Soft Warnings', $generationDiagnostics['soft_warnings'] ?? 0],
                    ['Quality', ($generationDiagnostics['quality_score'] ?? 0) . '%'],
                    ['Quality Band', str_replace('_', ' ', $generationDiagnostics['quality_band'] ?? 'missing')],
                    ['Solver Attempts', $generationDiagnostics['solver_attempts'] ?? 0],
                    ['Failed Attempts', $generationDiagnostics['failed_solver_attempts'] ?? 0],
                    ['Open Actions', $generationDiagnostics['open_resolution_actions'] ?? 0],
                    ['Publish Blocks', $generationDiagnostics['blocking_publish_checks'] ?? 0],
                    ['Impact Rows', $generationDiagnostics['impact_preview_records'] ?? 0],
                    ['Stale Inputs', $generationDiagnostics['stale_input_sources'] ?? 0],
                    ['Blockers', $generationDiagnostics['blocker_total'] ?? 0],
                ] as [$label, $value])
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="fw-semibold text-truncate" title="{{ $value }}">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $generationDiagnostics['recommended_action'] ?? 'Review generation validation.' }}</span>
            <a href="{{ route('academics.pmc.timetable-generator.index') }}">Open generator validation source</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Publish And Freeze Readiness</div>
                <div class="small text-muted">Official version, lifecycle workflow, revision queue, publish checks, notification failures, and sync coverage.</div>
            </div>
            <span class="badge text-bg-{{ ($publishReadinessDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $publishReadinessDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="card-body py-2">
            <div class="row g-2 text-center">
                @foreach([
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
                    ['Affected Students', $publishReadinessDiagnostics['affected_students'] ?? 0],
                    ['Affected Faculty', $publishReadinessDiagnostics['affected_faculty'] ?? 0],
                    ['Blockers', $publishReadinessDiagnostics['blocker_total'] ?? 0],
                ] as [$label, $value])
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="fw-semibold text-truncate" title="{{ $value }}">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $publishReadinessDiagnostics['recommended_action'] ?? 'Review publish and freeze readiness.' }}</span>
            <span class="d-flex flex-wrap gap-2">
                <a href="{{ route('academics.pmc.timetable-versions-v041.index') }}">Open version lifecycle board</a>
                <a href="{{ route('academics.pmc.timetable-reports.index', ['status' => 'failed']) }}">Failed notification report</a>
            </span>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Substitution Emergency Desk</div>
                <div class="small text-muted">Today/tomorrow uncovered classes, weak substitutes, same-day change requests, repeated substitution risk, and failed notices.</div>
            </div>
            <span class="badge text-bg-{{ ($substitutionEmergencyDiagnostics['status'] ?? '') === 'ready' ? 'success' : 'warning' }}">{{ str_replace('_', ' ', $substitutionEmergencyDiagnostics['status'] ?? 'attention_required') }}</span>
        </div>
        <div class="card-body py-2">
            <div class="row g-2 text-center">
                @foreach([
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
        <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
            <span>{{ $substitutionEmergencyDiagnostics['recommended_action'] ?? 'Review substitution desk.' }}</span>
            <a href="{{ route('academics.pmc.substitution-intelligence.index') }}">Open substitution desk</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Input Readiness</div>
                <div class="list-group list-group-flush">
                    @foreach($readiness as $item)
                        <div class="list-group-item py-2 d-flex justify-content-between gap-2">
                            <span>{{ $item['label'] }}</span>
                            <span class="badge text-bg-{{ $item['ready'] ? 'success' : 'warning' }}">{{ $item['ready'] ? 'ready' : 'blocked' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex justify-content-between">
                    <span class="fw-semibold">Latest Generation</span>
                    <a class="small" href="{{ route('academics.pmc.timetable-generator.index') }}">Open</a>
                </div>
                <div class="card-body">
                    @if($latestRun)
                        <div class="fw-semibold">{{ $latestRun->title }}</div>
                        <div class="small text-muted">{{ $latestRun->strategy }} | {{ $latestRun->status }}</div>
                        <div class="row g-2 mt-2">
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Scheduled</div><div class="fw-semibold">{{ $latestRun->scheduled_count }}</div></div></div>
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Unscheduled</div><div class="fw-semibold">{{ $latestRun->unscheduled_count }}</div></div></div>
                            <div class="col"><div class="border rounded p-2"><div class="small text-muted">Score</div><div class="fw-semibold">{{ $latestRun->quality_score }}%</div></div></div>
                        </div>
                    @else
                        <div class="text-muted">No generation run yet.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Notification Log</div>
                <div class="list-group list-group-flush">
                    @forelse($notifications as $notification)
                        <a class="list-group-item list-group-item-action py-2" href="{{ route('academics.pmc.timetable-reports.index', ['notification_type' => $notification->notification_type]) }}">
                            <div class="fw-semibold">{{ $notification->title }}</div>
                            <div class="small text-muted">{{ $notification->notification_type }} | {{ $notification->recipient_type }} | {{ $notification->status }}</div>
                        </a>
                    @empty
                        <div class="list-group-item text-muted">No notifications logged.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header py-2 d-flex justify-content-between">
            <span class="fw-semibold">Constraint Board</span>
            <a href="{{ route('academics.pmc.timetable-planner.index') }}" class="small">Planner</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Constraint</th><th>Severity</th><th>Affected</th><th>Recommended Fix</th></tr></thead>
                <tbody>
                    @forelse($constraints as $constraint)
                        <tr>
                            <td><div class="fw-semibold">{{ $constraint->title }}</div><div class="small text-muted">{{ $constraint->description }}</div></td>
                            <td><span class="badge text-bg-{{ $constraint->severity === 'hard' ? 'danger' : 'warning' }}">{{ $constraint->severity }}</span></td>
                            <td>{{ $constraint->affected_type }} #{{ $constraint->affected_key }}</td>
                            <td>{{ $constraint->recommended_fix }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No constraints found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
