@php
    $severityClasses = [
        'urgent' => 'danger',
        'high' => 'warning',
        'normal' => 'secondary',
    ];
@endphp

<div class="card border-0 shadow-sm mb-3 admission-action-center">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;letter-spacing:.02em">v0.034</div>
                <h5 class="mb-1 fw-bold">{{ $actionCenter['title'] }}</h5>
                <div class="text-muted small">Immediate blockers, next best action, quick commands, and recent operational activity.</div>
            </div>
            <div>@include('admission.partials._action-button', ['action' => $actionCenter['primary'], 'size' => 'sm'])</div>
        </div>

        <div class="row g-2 mb-3">
            @foreach($actionCenter['metrics'] as $label => $value)
                <div class="col-6 col-lg-3">
                    <div class="border rounded px-2 py-2 bg-light">
                        <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div>
                        <div class="fw-bold">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="fw-semibold small mb-2">Needs attention</div>
                @forelse($actionCenter['blockers'] as $blocker)
                    @php $class = $severityClasses[$blocker['severity']] ?? 'secondary'; @endphp
                    <div class="border rounded p-2 mb-2">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold small">{{ $blocker['title'] }}</span>
                            <span class="badge bg-{{ $class }} {{ $class === 'warning' ? 'text-dark' : '' }}">{{ ucfirst($blocker['severity']) }}</span>
                        </div>
                        <div class="small text-muted">{{ $blocker['detail'] }}</div>
                    </div>
                @empty
                    <div class="text-muted small border rounded p-2">No active blockers detected for this record.</div>
                @endforelse
            </div>

            <div class="col-lg-3">
                <div class="fw-semibold small mb-2">Quick commands</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($actionCenter['quick_actions'] as $action)
                        @include('admission.partials._action-button', ['action' => $action, 'size' => 'sm'])
                    @endforeach
                </div>
            </div>

            <div class="col-lg-4">
                <div class="fw-semibold small mb-2">Recent operating activity</div>
                @forelse($actionCenter['activity'] as $item)
                    <div class="d-flex gap-2 mb-2 small">
                        <span class="text-muted"><i class="bi bi-{{ $item['icon'] }}"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $item['type'] }}: {{ $item['label'] }}</div>
                            @if($item['detail'])
                                <div class="text-muted">{{ $item['detail'] }}</div>
                            @endif
                            <div class="text-muted">{{ optional($item['at'])->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small border rounded p-2">No recent reminder, call, or communication activity.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    .admission-action-center .btn { white-space: nowrap; }
    .admission-action-center .border { border-color: #e5e7eb !important; }
    @media (max-width: 575.98px) {
        .admission-action-center .btn { width: 100%; }
        .admission-action-center form { width: 100%; }
    }
</style>
@endpush
@endonce
