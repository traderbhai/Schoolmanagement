@extends('layouts.applicant')
@section('title', 'Applicant Dashboard')
@section('page-title', 'Applicant Dashboard')

@section('content')
@php
    $items = collect($checklist);
    $readyCount = $items->where('ready', true)->count();
    $totalCount = max($items->count(), 1);
    $blockedItems = $items->reject(fn ($item) => $item['ready'])->values();
    $nextItem = $blockedItems->first();
    $readinessPercent = round(($readyCount / $totalCount) * 100);
    $requiredDocs = \App\Models\RequiredDocument::where('program_id', $applicant->program_id)->where('is_active', true)->count();
    $uploadedDocs = $applicant->documents->count();
    $verifiedDocs = $applicant->documents->where('status', 'verified')->count();
    $verifiedPayments = $applicant->payments->where('status', 'verified')->count();
    $pendingPayments = $applicant->payments->where('status', 'pending')->count();
    $ownerFor = function (array $item): array {
        if ($item['ready']) {
            return ['label' => 'Complete', 'class' => 'ui-status-success', 'hint' => 'No action needed'];
        }

        $applicantRoutes = [
            'applicant.application.show',
            'applicant.documents.index',
            'applicant.registration-fee.show',
            'applicant.fees.index',
        ];

        if (in_array($item['route'], $applicantRoutes, true)) {
            return ['label' => 'Your action', 'class' => 'ui-status-warning', 'hint' => 'Open this step'];
        }

        return ['label' => 'Admission team', 'class' => 'ui-status-info', 'hint' => 'Track progress'];
    };
    $nextOwner = $nextItem ? $ownerFor($nextItem) : ['label' => 'Complete', 'class' => 'ui-status-success', 'hint' => 'No action needed'];
    $journeySteps = [
        ['label' => 'Draft', 'active' => true],
        ['label' => 'Submitted', 'active' => in_array($applicant->status, ['submitted', 'under_review', 'shortlisted', 'selected', 'enrolled'], true)],
        ['label' => 'Review', 'active' => in_array($applicant->status, ['under_review', 'shortlisted', 'selected', 'enrolled'], true)],
        ['label' => 'Offer', 'active' => (bool) $offerLetter],
        ['label' => 'Enrolled', 'active' => $enrollment?->status === 'completed'],
    ];
@endphp

<div class="container-fluid px-3 px-lg-4 py-3">
    <x-ui.page-header
        title="Applicant Dashboard"
        :subtitle="$applicant->application_number.' - '.$applicant->program->name.($applicant->batch ? ' - '.$applicant->batch->name : '')"
        action-label="Open Checklist"
        :action-route="route('applicant.checklist')"
        action-icon="bi-list-check"
    />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="ui-kpi-tile-icon bg-primary-subtle text-primary">
                        <i class="bi {{ $nextItem ? 'bi-arrow-right-circle' : 'bi-check-circle' }}"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Next required action</div>
                        <h2 class="h5 fw-bold mb-1">{{ $nextItem['label'] ?? 'No pending applicant action' }}</h2>
                        <div class="text-muted small">
                            @if($nextItem)
                                {{ $nextItem['blockers'][0] ?? 'Open this step and complete the pending requirement.' }}
                            @else
                                Your applicant-side checklist is complete. Watch status, offer, and admission operation updates.
                            @endif
                        </div>
                        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="ui-status {{ $nextOwner['class'] }}">Owner: {{ $nextOwner['label'] }}</span>
                            <span class="small text-muted">{{ $nextOwner['hint'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span>
                    @if($nextItem && Route::has($nextItem['route']))
                        <a href="{{ route($nextItem['route']) }}" class="btn btn-primary btn-sm">
                            {{ $nextItem['action_label'] ?? 'Continue' }} <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    @else
                        <a href="{{ route('applicant.status') }}" class="btn btn-outline-primary btn-sm">Track Status</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="ui-kpi-strip">
        <a href="{{ route('applicant.status') }}" class="ui-kpi-tile ui-kpi-link">
            <span class="ui-kpi-tile-icon"><i class="bi bi-activity"></i></span>
            <span>
                <span class="ui-kpi-label">Current status</span>
                <span class="ui-kpi-value fs-6">{{ $applicant->status_label }}</span>
                <span class="ui-kpi-hint">Open status tracker</span>
            </span>
        </a>
        <a href="{{ route('applicant.checklist') }}" class="ui-kpi-tile ui-kpi-link">
            <span class="ui-kpi-tile-icon"><i class="bi bi-ui-checks"></i></span>
            <span>
                <span class="ui-kpi-label">Readiness</span>
                <span class="ui-kpi-value">{{ $readinessPercent }}%</span>
                <span class="ui-kpi-hint">{{ $blockedItems->count() }} blocker{{ $blockedItems->count() === 1 ? '' : 's' }}</span>
            </span>
        </a>
        <a href="{{ route('applicant.documents.index') }}" class="ui-kpi-tile ui-kpi-link">
            <span class="ui-kpi-tile-icon"><i class="bi bi-file-earmark-check"></i></span>
            <span>
                <span class="ui-kpi-label">Documents</span>
                <span class="ui-kpi-value">{{ $uploadedDocs }}/{{ $requiredDocs }}</span>
                <span class="ui-kpi-hint">{{ $verifiedDocs }} verified</span>
            </span>
        </a>
        <a href="{{ route('applicant.fees.index') }}" class="ui-kpi-tile ui-kpi-link">
            <span class="ui-kpi-tile-icon"><i class="bi bi-credit-card"></i></span>
            <span>
                <span class="ui-kpi-label">Admission payments</span>
                <span class="ui-kpi-value">{{ $verifiedPayments }}</span>
                <span class="ui-kpi-hint">{{ $pendingPayments }} pending verification</span>
            </span>
        </a>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-signpost-split me-2"></i>Admission Journey</span>
                    <a href="{{ route('applicant.status') }}" class="btn btn-outline-secondary btn-sm">Full Tracker</a>
                </div>
                <div class="card-body py-3">
                    <div class="small text-muted mb-3">
                        Follow this path in order. Items marked <strong>Your action</strong> need you to upload, pay, or complete details. Items marked <strong>Admission team</strong> are staff review, assessment, offer, seat, or handoff steps.
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @foreach($journeySteps as $step)
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center {{ $step['active'] ? 'bg-success text-white' : 'bg-light text-muted border' }}" style="width:28px;height:28px;">
                                    <i class="bi {{ $step['active'] ? 'bi-check-lg' : 'bi-circle' }} small"></i>
                                </span>
                                <span class="small fw-semibold {{ $step['active'] ? 'text-success' : 'text-muted' }}">{{ $step['label'] }}</span>
                            </div>
                            @if(!$loop->last)
                                <span class="border-top flex-grow-1 d-none d-md-inline-block" style="min-width:24px;max-width:60px;"></span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-list-check me-2"></i>Your Admission Tasks</span>
                    <span class="small text-muted">{{ $readyCount }}/{{ $totalCount }} ready</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Step</th>
                                <th>Status</th>
                                <th>Owner</th>
                                <th>What this means</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php($owner = $ownerFor($item))
                                <tr>
                                    <td class="fw-semibold">{{ $item['label'] }}</td>
                                    <td>
                                        <span class="ui-status {{ $item['ready'] ? 'ui-status-success' : 'ui-status-warning' }}">
                                            {{ $item['ready'] ? 'Ready' : 'Needs action' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ui-status {{ $owner['class'] }}">{{ $owner['label'] }}</span>
                                    </td>
                                    <td class="small text-muted">
                                        @if($item['ready'])
                                            This step is complete.
                                        @else
                                            {{ $item['blockers'][0] ?? 'Open this step to resolve the blocker.' }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                        @if(Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" class="btn btn-outline-primary btn-sm">
                                                {{ $item['action_label'] ?? ($item['ready'] ? 'View' : 'Resolve') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-person-vcard me-2"></i>Application Summary</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Applicant</dt>
                        <dd class="col-7">{{ auth()->user()->name }}</dd>
                        <dt class="col-5 text-muted">Application</dt>
                        <dd class="col-7">{{ $applicant->application_number }}</dd>
                        <dt class="col-5 text-muted">Program</dt>
                        <dd class="col-7">{{ $applicant->program->name }}</dd>
                        <dt class="col-5 text-muted">Batch</dt>
                        <dd class="col-7">{{ $applicant->batch?->name ?? 'Batch not assigned yet' }}</dd>
                        <dt class="col-5 text-muted">Submitted</dt>
                        <dd class="col-7">{{ $applicant->applied_at?->format('d M Y') ?? 'Not submitted' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-lightning-charge me-2"></i>Quick Links</div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('applicant.application.show') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Application Form <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="{{ route('applicant.admission-operations.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Assessment, Waitlist & Joining <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="{{ route('applicant.offer-letters.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Offer Letters <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="{{ route('applicant.notifications.edit') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        Notification Preferences <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
