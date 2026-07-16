@php
    $statusValue = $status ?? 'attention_required';
    $statusClass = $statusValue === 'ready' ? 'success' : 'warning';
    $metricColumnClass = $metricColumnClass ?? 'col-6 col-md';
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="fw-semibold">{{ $title }}</div>
            <div class="small text-muted">{{ $subtitle }}</div>
        </div>
        <span class="badge text-bg-{{ $statusClass }}">{{ str_replace('_', ' ', $statusValue) }}</span>
    </div>
    <div class="row g-0 text-center">
        @foreach($metrics as [$label, $value])
            <div class="{{ $metricColumnClass }} border-top border-end py-2">
                <div class="small text-muted">{{ $label }}</div>
                <div class="fw-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>
    <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
        <span>{{ $recommendedAction }}</span>
        <a href="{{ $sourceUrl }}">{{ $sourceLabel }}</a>
    </div>
</div>
