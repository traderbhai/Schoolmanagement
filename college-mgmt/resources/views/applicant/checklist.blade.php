@extends('layouts.applicant')
@section('title', 'Admission Checklist')
@section('page-title', 'Admission Checklist')

@section('content')
@php
    $items = collect($checklist);
    $readyCount = $items->where('ready', true)->count();
    $blockedItems = $items->reject(fn ($item) => $item['ready'])->values();
    $readinessPercent = $items->isEmpty() ? 0 : round(($readyCount / $items->count()) * 100);
    $ownerFor = function (array $item): array {
        if ($item['ready']) {
            return ['label' => 'Complete', 'class' => 'ui-status-success'];
        }

        $applicantRoutes = [
            'applicant.application.show',
            'applicant.documents.index',
            'applicant.registration-fee.show',
            'applicant.fees.index',
        ];

        if (in_array($item['route'], $applicantRoutes, true)) {
            return ['label' => 'Your action', 'class' => 'ui-status-warning'];
        }

        return ['label' => 'Admission team', 'class' => 'ui-status-info'];
    };
@endphp

<div class="container-fluid px-3 px-lg-4 py-3">
    <x-ui.page-header
        title="Admission Checklist"
        :subtitle="$applicant->application_number.' - '.$applicant->program?->name.' - '.$readinessPercent.'% ready'"
        action-label="Back To Dashboard"
        :action-route="route('applicant.dashboard')"
        action-icon="bi-speedometer2"
    />

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="ui-kpi-tile">
                <span class="ui-kpi-tile-icon"><i class="bi bi-ui-checks"></i></span>
                <span>
                    <span class="ui-kpi-label">Checklist progress</span>
                    <span class="ui-kpi-value">{{ $readinessPercent }}%</span>
                    <span class="ui-kpi-hint">{{ $readyCount }}/{{ $items->count() }} steps ready</span>
                </span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ui-kpi-tile">
                <span class="ui-kpi-tile-icon"><i class="bi bi-exclamation-triangle"></i></span>
                <span>
                    <span class="ui-kpi-label">Open blockers</span>
                    <span class="ui-kpi-value">{{ $blockedItems->count() }}</span>
                    <span class="ui-kpi-hint">Resolve these to move forward</span>
                </span>
            </div>
        </div>
        <div class="col-md-4">
            <a href="{{ route('applicant.status') }}" class="ui-kpi-tile ui-kpi-link">
                <span class="ui-kpi-tile-icon"><i class="bi bi-activity"></i></span>
                <span>
                    <span class="ui-kpi-label">Current status</span>
                    <span class="ui-kpi-value fs-6">{{ $applicant->status_label }}</span>
                    <span class="ui-kpi-hint">Open status tracker</span>
                </span>
            </a>
        </div>
    </div>

    @if($blockedItems->isNotEmpty())
        <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div class="small">
                <strong>Focus first:</strong> {{ $blockedItems->first()['label'] }} -
                {{ $blockedItems->first()['blockers'][0] ?? 'open this step and complete the pending requirement.' }}
                <span class="d-block mt-1">Owner: {{ $ownerFor($blockedItems->first())['label'] }}.</span>
            </div>
        </div>
    @else
        <div class="alert alert-success d-flex align-items-start gap-2 py-2 mb-3">
            <i class="bi bi-check-circle-fill mt-1"></i>
            <div class="small"><strong>Applicant checklist ready.</strong> Continue watching status, assessment, offer, and joining updates.</div>
        </div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-list-check me-2"></i>Readiness Details</span>
            <span class="{{ $applicant->status_badge }}">{{ $applicant->status_label }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 22%">Step</th>
                        <th scope="col" style="width: 12%">Status</th>
                        <th scope="col" style="width: 14%">Owner</th>
                        <th scope="col">Blocker / confirmation</th>
                        <th scope="col" class="text-end" style="width: 14%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php($owner = $ownerFor($item))
                        <tr>
                            <td class="fw-semibold">{{ $item['label'] }}</td>
                            <td>
                                <span class="ui-status {{ $item['ready'] ? 'ui-status-success' : 'ui-status-warning' }}">
                                    {{ $item['ready'] ? 'Ready' : 'Blocked' }}
                                </span>
                            </td>
                            <td>
                                <span class="ui-status {{ $owner['class'] }}">{{ $owner['label'] }}</span>
                            </td>
                            <td class="small text-muted">
                                @if($item['ready'])
                                    This step is complete.
                                @else
                                    <ul class="mb-0 ps-3">
                                        @foreach($item['blockers'] as $blocker)
                                            <li>{{ $blocker }}</li>
                                        @endforeach
                                    </ul>
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
@endsection
