@props(['title', 'meta' => null, 'icon' => 'bi-circle-fill'])

<div {{ $attributes->merge(['class' => 'ui-timeline-item']) }}>
    <span class="ui-timeline-dot"><i class="bi {{ $icon }}"></i></span>
    <div>
        <div class="ui-timeline-title">{{ $title }}</div>
        @if($meta)
            <div class="ui-timeline-meta">{{ $meta }}</div>
        @endif
        <div class="ui-timeline-body">{{ $slot }}</div>
    </div>
</div>
