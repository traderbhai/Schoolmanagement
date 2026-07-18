@php
    $btnClass = str_starts_with($action['style'], 'outline-') ? 'btn-' . $action['style'] : 'btn-' . $action['style'];
    $icon = $action['icon'] ?? 'arrow-right';
    $size = $size ?? 'sm';
@endphp

@if(($action['type'] ?? 'link') === 'post')
    <form method="POST" action="{{ $action['action'] }}" class="d-inline">
        @csrf
        @foreach(($action['fields'] ?? []) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button class="btn {{ $btnClass }} btn-{{ $size }}">
            <i class="bi bi-{{ $icon }} me-1"></i>{{ $action['label'] }}
        </button>
    </form>
@elseif(($action['type'] ?? 'link') === 'modal')
    <button type="button" class="btn {{ $btnClass }} btn-{{ $size }}" data-bs-toggle="modal" data-bs-target="{{ $action['target'] }}">
        <i class="bi bi-{{ $icon }} me-1"></i>{{ $action['label'] }}
    </button>
@else
    <a class="btn {{ $btnClass }} btn-{{ $size }}" href="{{ $action['href'] }}">
        <i class="bi bi-{{ $icon }} me-1"></i>{{ $action['label'] }}
    </a>
@endif
