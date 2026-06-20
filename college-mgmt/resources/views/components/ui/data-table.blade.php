@props(['caption' => null])

<div {{ $attributes->merge(['class' => 'ui-data-table table-responsive']) }}>
    <table class="table table-hover align-middle mb-0">
        @if($caption)
            <caption class="visually-hidden">{{ $caption }}</caption>
        @endif
        {{ $slot }}
    </table>
</div>
