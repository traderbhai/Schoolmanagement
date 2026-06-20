@props(['action' => url()->current(), 'summary' => null])

<form method="GET" action="{{ $action }}" {{ $attributes->merge(['class' => 'ui-filter-bar']) }}>
    <div class="ui-filter-fields">
        {{ $slot }}
    </div>
    <div class="ui-filter-actions">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
        <a href="{{ $action }}" class="btn btn-outline-secondary btn-sm">Clear</a>
    </div>
    @if($summary)
        <div class="ui-filter-summary">{{ $summary }}</div>
    @endif
</form>
