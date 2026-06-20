@props(['items' => []])

<div {{ $attributes->merge(['class' => 'ui-kpi-strip']) }}>
    @foreach($items as $item)
        @php
            $href = $item['href'] ?? null;
            $tone = $item['tone'] ?? 'primary';
            $icon = $item['icon'] ?? 'bi-speedometer2';
        @endphp
        @if($href)
            <a href="{{ $href }}" class="ui-kpi-tile ui-kpi-{{ $tone }} ui-kpi-link">
                <span class="ui-kpi-tile-icon"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <span class="ui-kpi-label">{{ $item['label'] ?? '' }}</span>
                    <span class="ui-kpi-value">{{ $item['value'] ?? 0 }}</span>
                    @if(!empty($item['hint']))
                        <span class="ui-kpi-hint">{{ $item['hint'] }}</span>
                    @endif
                </span>
            </a>
        @else
            <div class="ui-kpi-tile ui-kpi-{{ $tone }}">
                <span class="ui-kpi-tile-icon"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <span class="ui-kpi-label">{{ $item['label'] ?? '' }}</span>
                    <span class="ui-kpi-value">{{ $item['value'] ?? 0 }}</span>
                    @if(!empty($item['hint']))
                        <span class="ui-kpi-hint">{{ $item['hint'] }}</span>
                    @endif
                </span>
            </div>
        @endif
    @endforeach
</div>
