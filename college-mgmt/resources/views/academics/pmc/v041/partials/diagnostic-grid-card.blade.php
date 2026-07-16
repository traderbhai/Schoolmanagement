@php
    $statusValue = $status ?? 'attention_required';
    $statusClass = $statusValue === 'ready' ? 'success' : 'warning';
    $metricColumnClass = $metricColumnClass ?? 'col-6 col-md-4 col-xl-2';
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-semibold">{{ $title }}</div>
            <div class="small text-muted">{{ $subtitle }}</div>
        </div>
        <span class="badge text-bg-{{ $statusClass }}">{{ str_replace('_', ' ', $statusValue) }}</span>
    </div>
    <div class="card-body py-2">
        <div class="row g-2 text-center">
            @foreach($metrics as [$label, $value])
                <div class="{{ $metricColumnClass }}">
                    <div class="border rounded p-2 h-100">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fw-semibold text-truncate" title="{{ $value }}">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer py-2 small d-flex flex-wrap justify-content-between gap-2">
        <span>{{ $recommendedAction }}</span>
        @if(!empty($sourceLinks ?? []))
            <span class="d-flex flex-wrap gap-2">
                @foreach($sourceLinks as $sourceLink)
                    <a href="{{ $sourceLink['url'] }}">{{ $sourceLink['label'] }}</a>
                @endforeach
            </span>
        @elseif(isset($sourceUrl))
            <a href="{{ $sourceUrl }}">{{ $sourceLabel ?? 'Open source list' }}</a>
        @endif
    </div>
</div>
