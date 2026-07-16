@props([
    'href' => null,
    'label' => '',
    'value' => 0,
    'ariaLabel' => null,
])

@if($href)
    <a class="text-decoration-none d-block h-100" href="{{ $href }}" aria-label="{{ $ariaLabel ?? 'Open ' . $label . ' source list' }}">
        <div {{ $attributes->merge(['class' => 'card shadow-sm h-100']) }}>
            <div class="card-body py-2">
                <div class="small text-muted">{{ $label }}</div>
                <div class="h4 mb-0">{{ $value }}</div>
            </div>
        </div>
    </a>
@else
    <div {{ $attributes->merge(['class' => 'card shadow-sm h-100']) }}>
        <div class="card-body py-2">
            <div class="small text-muted">{{ $label }}</div>
            <div class="h4 mb-0">{{ $value }}</div>
        </div>
    </div>
@endif
