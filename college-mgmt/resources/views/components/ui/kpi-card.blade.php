@props([
    'href' => null,
    'tone' => 'blue',
    'icon' => 'bi-speedometer2',
    'value' => 0,
    'valueSize' => null,
    'label' => '',
    'trend' => null,
    'trendIcon' => null,
    'trendTone' => null,
])

@php
    $cardClass = 'kpi-card kpi-' . $tone;
    $valueStyle = $valueSize ? 'font-size:' . $valueSize : null;
@endphp

@if($href)
    <a href="{{ $href }}" class="text-decoration-none">
        <div {{ $attributes->merge(['class' => $cardClass]) }}>
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon"><i class="bi {{ $icon }}"></i></div>
                <div>
                    <div class="kpi-value"@if($valueStyle) style="{{ $valueStyle }}"@endif>{{ $value }}</div>
                    <div class="kpi-label">{{ $label }}</div>
                </div>
            </div>
            @if($trend)
                <div class="kpi-trend {{ $trendTone }}">
                    @if($trendIcon)<i class="bi {{ $trendIcon }} me-1"></i>@endif{{ $trend }}
                </div>
            @endif
        </div>
    </a>
@else
    <div {{ $attributes->merge(['class' => $cardClass]) }}>
        <div class="d-flex align-items-center gap-3">
            <div class="kpi-icon"><i class="bi {{ $icon }}"></i></div>
            <div>
                <div class="kpi-value"@if($valueStyle) style="{{ $valueStyle }}"@endif>{{ $value }}</div>
                <div class="kpi-label">{{ $label }}</div>
            </div>
        </div>
        @if($trend)
            <div class="kpi-trend {{ $trendTone }}">
                @if($trendIcon)<i class="bi {{ $trendIcon }} me-1"></i>@endif{{ $trend }}
            </div>
        @endif
    </div>
@endif
