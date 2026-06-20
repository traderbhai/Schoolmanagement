@props([
    'title',
    'subtitle' => null,
    'actionLabel' => null,
    'actionRoute' => null,
    'actionIcon' => 'bi-plus-lg',
])

<div {{ $attributes->merge(['class' => 'ui-page-header']) }}>
    <div>
        <h1 class="ui-page-title">{{ $title }}</h1>
        @if($subtitle)
            <div class="ui-page-subtitle">{{ $subtitle }}</div>
        @endif
    </div>
    @if($actionLabel && $actionRoute)
        <a href="{{ $actionRoute }}" class="btn btn-primary btn-sm">
            <i class="bi {{ $actionIcon }} me-1"></i>{{ $actionLabel }}
        </a>
    @endif
</div>
