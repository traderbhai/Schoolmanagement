@extends('layouts.admin')
@section('title', 'Feature Access — ' . $role->name)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.roles.feature-access.index') }}">Feature Matrix</a></li>
                    <li class="breadcrumb-item active">{{ $role->name }}</li>
                </ol>
            </nav>
            <h4 class="fw-bold mb-0">{{ ucwords(str_replace('_', ' ', $role->name)) }}</h4>
            <span class="text-muted small">Set feature access levels for this role</span>
        </div>
        <a href="{{ route('admin.roles.feature-access.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid-3x3 me-1"></i> Full Matrix
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.roles.feature-access.update', $role) }}">
        @csrf
        @method('PUT')

        @php
            $categories = collect($features)->groupBy('category');
            $currentAccess = $currentAccess ?? collect();
            $levels = ['', 'view', 'create', 'edit', 'approve', 'delete'];
            $levelColors = [
                ''        => 'secondary',
                'view'    => 'primary',
                'create'  => 'success',
                'edit'    => 'warning',
                'approve' => 'info',
                'delete'  => 'danger',
            ];
        @endphp

        <div class="row g-4">
            @foreach($categories as $category => $catFeatures)
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0">
                        <h6 class="fw-semibold text-uppercase text-muted mb-0" style="font-size:.7rem;letter-spacing:.07em">
                            {{ $category }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach($catFeatures as $feature)
                            @php
                                $current = $currentAccess[$feature['code']] ?? '';
                            @endphp
                            <div class="col-md-4">
                                <label class="form-label fw-medium mb-1">{{ $feature['label'] }}</label>
                                <div class="text-muted mb-2" style="font-size:.72rem">{{ $feature['code'] }}</div>
                                <div class="d-flex gap-1 flex-wrap">
                                    @foreach($levels as $level)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio"
                                               name="access[{{ $feature['code'] }}]"
                                               id="fa_{{ $feature['code'] }}_{{ $level ?: 'none' }}"
                                               value="{{ $level }}"
                                               {{ $current === $level ? 'checked' : '' }}>
                                        <label class="form-check-label" for="fa_{{ $feature['code'] }}_{{ $level ?: 'none' }}">
                                            @if($level)
                                            <span class="badge bg-{{ $levelColors[$level] }}-subtle text-{{ $levelColors[$level] }} border border-{{ $levelColors[$level] }}-subtle"
                                                  style="font-size:.7rem">{{ $level }}</span>
                                            @else
                                            <span class="badge bg-light text-muted border" style="font-size:.7rem">none</span>
                                            @endif
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Save Feature Access
                </button>
                <a href="{{ route('admin.roles.feature-access.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
