@extends('layouts.admin')

@section('title', 'Admission Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0 fw-bold">Admission CRM Dashboard</h2>
        <small class="text-muted">{{ now()->format('l, d F Y') }}</small>
    </div>
    <div>
        @if(auth()->user()->hasRole('admission_head'))
            <span class="badge bg-danger fs-6"><i class="bi bi-shield-fill-check me-1"></i>Admission Head</span>
        @else
            <span class="badge bg-info fs-6"><i class="bi bi-person-badge me-1"></i>Admission Officer</span>
        @endif
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-primary">{{ $kpis['total'] }}</div>
                <div class="small text-muted">Total Applied</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-info">{{ $kpis['submitted'] }}</div>
                <div class="small text-muted">Submitted</div>
                @if($kpis['submitted_today'] > 0)
                    <div class="badge bg-success mt-1">+{{ $kpis['submitted_today'] }} today</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-warning">{{ $kpis['under_review'] }}</div>
                <div class="small text-muted">Under Review</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-cyan">{{ $kpis['shortlisted'] }}</div>
                <div class="small text-muted">Shortlisted</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-success">{{ $kpis['selected'] }}</div>
                <div class="small text-muted">Selected</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-1 fw-bold text-danger">{{ $kpis['rejected'] }}</div>
                <div class="small text-muted">Rejected</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Pipeline Funnel --}}
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-funnel me-1 text-primary"></i> Admission Pipeline
            </div>
            <div class="card-body">
                @php
                    $labels = ['submitted' => 'Submitted', 'under_review' => 'Under Review', 'shortlisted' => 'Shortlisted', 'selected' => 'Selected'];
                    $colors = ['submitted' => 'primary', 'under_review' => 'warning', 'shortlisted' => 'info', 'selected' => 'success'];
                @endphp
                @foreach($pipeline as $status => $count)
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-medium">{{ $labels[$status] ?? $status }}</span>
                        <span class="badge bg-{{ $colors[$status] ?? 'secondary' }}">{{ $count }}</span>
                    </div>
                    <div class="progress" style="height: 24px;">
                        <div class="progress-bar bg-{{ $colors[$status] ?? 'secondary' }}"
                             style="width: {{ $pipelineMax > 0 ? round($count/$pipelineMax*100) : 0 }}%">
                            @if($pipelineMax > 0 && $count > 0){{ round($count/$pipelineMax*100) }}%@endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Follow-ups Due Today --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 fw-bold">
                <i class="bi bi-calendar-check me-1 text-danger"></i> Follow-ups Due (Next 7 Days)
            </div>
            <div class="card-body p-0">
                @if($followups->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle fs-3"></i><br>No follow-ups due
                    </div>
                @else
                <div class="list-group list-group-flush">
                    @foreach($followups as $log)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small">{{ $log->applicant->user->name ?? 'N/A' }}</div>
                                <div class="text-muted" style="font-size:0.78rem">{{ $log->applicant->application_number ?? '' }}</div>
                            </div>
                            <div class="text-end">
                                <div class="badge bg-warning text-dark small">{{ $log->next_followup_date->format('d M') }}</div>
                                <div>
                                    <a href="{{ route('admission.applicants.show', $log->applicant_id) }}" class="btn btn-xs btn-outline-primary py-0 px-1" style="font-size:0.7rem">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Interactions --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 fw-bold">
        <i class="bi bi-chat-dots me-1 text-info"></i> Recent Interactions
    </div>
    @if($recentLogs->isEmpty())
        <div class="card-body text-center text-muted py-4">No interactions logged yet.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Applicant</th>
                    <th>Type</th>
                    <th>Outcome</th>
                    <th>Notes</th>
                    <th>Logged By</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $typeIcons = ['call'=>'telephone','email'=>'envelope','whatsapp'=>'whatsapp','walk_in'=>'person-walking','other'=>'three-dots'];
                    $outcomeColors = ['interested'=>'success','not_interested'=>'secondary','callback'=>'warning','enrolled'=>'primary','lost'=>'danger','follow_up'=>'info'];
                @endphp
                @foreach($recentLogs as $log)
                <tr>
                    <td>
                        <a href="{{ route('admission.applicants.show', $log->applicant_id) }}" class="text-decoration-none fw-semibold">
                            {{ $log->applicant->user->name ?? 'N/A' }}
                        </a>
                    </td>
                    <td><i class="bi bi-{{ $typeIcons[$log->interaction_type] ?? 'chat' }} me-1"></i>{{ ucfirst(str_replace('_',' ',$log->interaction_type)) }}</td>
                    <td><span class="badge bg-{{ $outcomeColors[$log->outcome] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$log->outcome)) }}</span></td>
                    <td class="text-truncate" style="max-width:200px">{{ $log->notes }}</td>
                    <td class="small">{{ $log->loggedBy->name ?? 'N/A' }}</td>
                    <td class="small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
