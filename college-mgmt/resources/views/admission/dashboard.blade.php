@extends('layouts.admin')

@section('title', 'Admission Dashboard')

@push('styles')
<style>
    .admission-compact .card { border-radius: 6px; }
    .admission-compact .card-body { padding: .75rem; }
    .admission-compact .table > :not(caption) > * > * { padding: .45rem .6rem; }
    .admission-compact .metric-link { display:block; color:inherit; text-decoration:none; }
    .admission-compact .metric-link:hover .card,
    .admission-compact .funnel-link:hover { border-color:#0d6efd !important; box-shadow:0 .125rem .45rem rgba(13,110,253,.18); }
    .admission-compact .metric-value { font-size:1.55rem; line-height:1.1; }
</style>
@endpush

@section('content')
<div class="admission-compact">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0 fw-bold">Admission CRM Dashboard</h3>
        <small class="text-muted">{{ now()->format('l, d F Y') }}</small>
    </div>
    <div>
        @if(app(\App\Services\DepartmentHierarchyService::class)->canApproveAdmission(auth()->user()))
            <span class="badge bg-danger fs-6"><i class="bi bi-shield-fill-check me-1"></i>Admission Leadership</span>
        @else
            <span class="badge bg-info fs-6"><i class="bi bi-person-badge me-1"></i>Admission Team</span>
        @endif
    </div>
</div>

{{-- KPI Cards --}}
<div class="row g-2 mb-3">
    @foreach([
        ['label'=>'Total Applied','value'=>$kpis['total'],'color'=>'primary','url'=>route('admission.applicants.index')],
        ['label'=>'Submitted','value'=>$kpis['submitted'],'color'=>'info','url'=>route('admission.applicants.index', ['status' => 'submitted']), 'sub'=>$kpis['submitted_today'] > 0 ? '+' . $kpis['submitted_today'] . ' today' : null],
        ['label'=>'Under Review','value'=>$kpis['under_review'],'color'=>'warning','url'=>route('admission.applicants.index', ['status' => 'under_review'])],
        ['label'=>'Shortlisted','value'=>$kpis['shortlisted'],'color'=>'info','url'=>route('admission.applicants.index', ['status' => 'shortlisted'])],
        ['label'=>'Selected','value'=>$kpis['selected'],'color'=>'success','url'=>route('admission.applicants.index', ['status' => 'selected'])],
        ['label'=>'Rejected','value'=>$kpis['rejected'],'color'=>'danger','url'=>route('admission.applicants.index', ['status' => 'rejected'])],
        ['label'=>'Docs Pending','value'=>$kpis['docs_pending'],'color'=>'warning','url'=>route('admission.documents.queue')],
        ['label'=>'Payments Pending','value'=>$kpis['payments_pending'] ?? 0,'color'=>'info','url'=>route('admission.payments.queue')],
    ] as $metric)
        <div class="col-6 col-md-3 col-xl-2">
            <a href="{{ $metric['url'] }}" class="metric-link">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted small">{{ $metric['label'] }}</div>
                                <div class="metric-value fw-bold text-{{ $metric['color'] }}">{{ $metric['value'] }}</div>
                            </div>
                            <i class="bi bi-arrow-up-right small text-muted"></i>
                        </div>
                        @if(!empty($metric['sub']))
                            <span class="badge bg-success mt-1">{{ $metric['sub'] }}</span>
                        @endif
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
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
                        <i class="bi bi-check-circle fs-3"></i>
                        <div class="fw-semibold text-dark mt-2">No follow-ups are due in the next 7 days</div>
                        <div class="small px-3 mt-1">Follow-ups appear here after staff log a counselling interaction and set a next follow-up date for an applicant visible to your Admission role.</div>
                        <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary mt-3">
                            <i class="bi bi-people me-1"></i>Open applicants
                        </a>
                    </div>
                @else
                <div class="list-group list-group-flush">
                    @foreach($followups as $log)
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small">{{ $log->applicant->user->name ?? 'Applicant name missing' }}</div>
                                <div class="text-muted" style="font-size:0.78rem">{{ $log->applicant->application_number ?? 'Application number missing' }}</div>
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

{{-- Upcoming Sessions --}}
@if($upcomingSessions->isNotEmpty())
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center fw-bold">
        <span><i class="bi bi-calendar-event me-1 text-primary"></i> Upcoming Sessions</span>
        <a href="{{ route('admission.sessions.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($upcomingSessions as $s)
            @php
                $typeColors = ['gd'=>'success','pi'=>'primary','wat'=>'warning','written_test'=>'info','aptitude'=>'secondary','presentation'=>'dark'];
                $stepType = $s->step->type ?? 'gd';
            @endphp
            <div class="col-md-4">
                <div class="card border h-100">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-{{ $typeColors[$stepType] ?? 'secondary' }}">{{ strtoupper($stepType) }}</span>
                            <span class="{{ $s->statusBadge }} small">{{ $s->statusLabel }}</span>
                        </div>
                        <div class="fw-semibold">{{ $s->session_name }}</div>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-calendar3 me-1"></i>{{ $s->scheduled_date->format('d M Y') }}<br>
                            <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($s->start_time)->format('H:i') }}<br>
                            <i class="bi bi-people me-1"></i>{{ $s->sessionApplicants->count() }} candidates
                        </div>
                        <a href="{{ route('admission.sessions.show', $s) }}" class="btn btn-sm btn-outline-primary mt-2 w-100">
                            <i class="bi bi-eye me-1"></i> View
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Recent Interactions --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent border-0 fw-bold">
        <i class="bi bi-chat-dots me-1 text-info"></i> Recent Interactions
    </div>
    @if($recentLogs->isEmpty())
        <div class="card-body text-center text-muted py-4">
            <div class="fw-semibold text-dark mb-1">No counselling interactions are logged yet</div>
            <div class="small mb-3">Open an applicant record, log the first call, email, WhatsApp, or walk-in outcome, and set the next follow-up date so this dashboard starts showing live activity.</div>
            <a href="{{ route('admission.applicants.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-people me-1"></i>Open applicants
            </a>
        </div>
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
                            {{ $log->applicant->user->name ?? 'Applicant name missing' }}
                        </a>
                    </td>
                    <td><i class="bi bi-{{ $typeIcons[$log->interaction_type] ?? 'chat' }} me-1"></i>{{ ucfirst(str_replace('_',' ',$log->interaction_type)) }}</td>
                    <td><span class="badge bg-{{ $outcomeColors[$log->outcome] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$log->outcome)) }}</span></td>
                    <td class="text-truncate" style="max-width:200px">{{ $log->notes ?: 'No notes recorded' }}</td>
                    <td class="small">{{ $log->loggedBy->name ?? 'Staff user missing' }}</td>
                    <td class="small text-muted">{{ $log->created_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Admission Funnel Section --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header">
        <span class="fw-600"><i class="bi bi-funnel-fill me-2 text-primary"></i>Admission Funnel</span>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            @php
                $funnelStages = [
                    ['label' => 'Leads',       'value' => $funnelData['leads'],       'icon' => 'bi-telephone',           'color' => 'primary', 'url' => route('admission.leads.index')],
                    ['label' => 'Applied',      'value' => $funnelData['applied'],     'icon' => 'bi-file-earmark-person', 'color' => 'info', 'url' => route('admission.applicants.index')],
                    ['label' => 'Shortlisted',  'value' => $funnelData['shortlisted'], 'icon' => 'bi-list-check',          'color' => 'warning', 'url' => route('admission.applicants.index', ['status' => 'shortlisted'])],
                    ['label' => 'Selected',     'value' => $funnelData['selected'],    'icon' => 'bi-person-check',        'color' => 'success', 'url' => route('admission.applicants.index', ['status' => 'selected'])],
                    ['label' => 'Enrolled',     'value' => $funnelData['enrolled'],    'icon' => 'bi-mortarboard',         'color' => 'secondary', 'url' => route('admission.enrollment.index')],
                ];
            @endphp
            @foreach($funnelStages as $i => $stage)
                <div class="text-center flex-fill">
                    <a href="{{ $stage['url'] }}" class="funnel-link d-block text-decoration-none text-reset rounded-2 p-2 border border-{{ $stage['color'] }} bg-{{ $stage['color'] }} bg-opacity-10">
                        <i class="bi {{ $stage['icon'] }} fs-5 text-{{ $stage['color'] }}"></i>
                        <div class="fw-bold fs-4 mt-1">{{ number_format($stage['value']) }}</div>
                        <div class="text-muted small">{{ $stage['label'] }}</div>
                        @if($i > 0 && $funnelStages[$i-1]['value'] > 0)
                            <div class="badge bg-{{ $stage['color'] }} mt-1">
                                {{ round(($stage['value'] / $funnelStages[$i-1]['value']) * 100, 1) }}% from prev.
                            </div>
                        @endif
                    </a>
                </div>
                @if($i < count($funnelStages) - 1)
                    <div class="text-muted fs-4 d-none d-md-block"><i class="bi bi-arrow-right"></i></div>
                @endif
            @endforeach
        </div>
    </div>
</div>

</div>
@endsection
