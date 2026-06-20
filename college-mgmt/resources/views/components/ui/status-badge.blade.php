@props(['status'])

@php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $tone = match (true) {
        in_array($normalized, ['approved', 'verified', 'paid', 'published', 'active', 'completed', 'done', 'selected'], true) => 'success',
        in_array($normalized, ['pending', 'waiting', 'draft', 'in_progress', 'review'], true) => 'warning',
        in_array($normalized, ['rejected', 'blocked', 'overdue', 'failed', 'cancelled', 'withdrawn'], true) => 'danger',
        in_array($normalized, ['frozen', 'official', 'locked', 'issued'], true) => 'info',
        default => 'secondary',
    };
@endphp

<span {{ $attributes->merge(['class' => "ui-status ui-status-{$tone}"]) }}>
    {{ ucwords(str_replace('_', ' ', $normalized)) }}
</span>
