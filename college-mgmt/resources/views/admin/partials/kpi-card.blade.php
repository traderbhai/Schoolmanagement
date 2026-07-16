<div class="col-sm-6 col-xl-3">
    <a href="{{ $href }}" class="text-decoration-none d-block h-100" aria-label="{{ $ariaLabel }}">
        <div class="kpi-card {{ $variant }} h-100">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">{{ $label }}</div>
                    <div class="kpi-value mt-1">{{ $value }}</div>
                    <div class="kpi-trend {{ $trendClass ?? '' }} mt-2"><i class="bi {{ $trendIcon }}"></i> {{ $trend }}</div>
                </div>
                <div class="kpi-icon"><i class="bi {{ $icon }}"></i></div>
            </div>
            @isset($progress)
                <div class="mt-2">
                    <div class="progress" style="height:4px;border-radius:2px;background:rgba(255,255,255,.3)">
                        <div class="progress-bar bg-white" style="width:{{ $progress }}%"></div>
                    </div>
                    <div class="text-xs mt-1 opacity-85">{{ $progressLabel }}</div>
                </div>
            @endisset
        </div>
    </a>
</div>
