@php
    $typeColors = ['gd'=>'success','pi'=>'primary','wat'=>'warning','written_test'=>'info','aptitude'=>'secondary','presentation'=>'dark'];
    $typeLabels = ['gd'=>'GD','pi'=>'PI','wat'=>'WAT','written_test'=>'Test','aptitude'=>'Apt','presentation'=>'Pres'];
    $stepType = $session->step->type ?? 'gd';
    $assigned = $session->sessionApplicants->count();
@endphp
<div class="card border-0 shadow-sm h-100">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-{{ $typeColors[$stepType] ?? 'secondary' }} fs-6">{{ $typeLabels[$stepType] ?? strtoupper($stepType) }}</span>
            <span class="{{ $session->statusBadge }}">{{ $session->statusLabel }}</span>
        </div>
        <h6 class="fw-bold mb-1">{{ $session->session_name }}</h6>
        <div class="small text-muted mb-1">
            <i class="bi bi-calendar3 me-1"></i>{{ $session->scheduled_date->format('d M Y') }}
            &nbsp;<i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }} – {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
        </div>
        @if($session->venue)
        <div class="small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i>{{ $session->venue }}</div>
        @endif
        <div class="small text-muted mb-2">
            <i class="bi bi-people me-1"></i>
            {{ $assigned }} assigned{{ $session->max_candidates ? ' / '.$session->max_candidates.' max' : '' }}
        </div>
        <div class="d-flex gap-1 mt-2">
            <a href="{{ route('admission.sessions.show', $session) }}" class="btn btn-sm btn-outline-primary flex-fill">
                <i class="bi bi-eye me-1"></i> View
            </a>
            @if($session->status === 'scheduled')
            <a href="{{ route('admission.sessions.edit', $session) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil"></i>
            </a>
            @endif
        </div>
    </div>
</div>
