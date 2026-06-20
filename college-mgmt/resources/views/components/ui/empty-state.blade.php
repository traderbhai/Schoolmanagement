@props([
    'title' => 'No records found',
    'message' => 'There is no matching database-backed data for the current view.',
    'icon' => 'bi-inbox',
])

<div {{ $attributes->merge(['class' => 'ui-empty-state']) }}>
    <i class="bi {{ $icon }}"></i>
    <div class="ui-empty-title">{{ $title }}</div>
    <div class="ui-empty-message">{{ $message }}</div>
    {{ $slot }}
</div>
