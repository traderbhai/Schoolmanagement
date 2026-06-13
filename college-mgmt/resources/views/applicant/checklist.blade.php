@extends('layouts.applicant')
@section('title', 'Admission Checklist')
@section('page-title', 'Admission Checklist')
@section('content')
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admission Checklist</h1>
            <div class="text-muted">{{ $applicant->application_number }} - {{ $applicant->program?->name }}</div>
        </div>
        <span class="{{ $applicant->status_badge }} fs-6">{{ $applicant->status_label }}</span>
    </div>

    <div class="row g-3">
        @foreach($checklist as $item)
            <div class="col-lg-6">
                <div class="card h-100 border-{{ $item['ready'] ? 'success' : 'warning' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-{{ $item['ready'] ? 'check-circle-fill text-success' : 'exclamation-triangle-fill text-warning' }} fs-3"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between gap-3">
                                    <h5 class="mb-1">{{ $item['label'] }}</h5>
                                    <span class="badge bg-{{ $item['ready'] ? 'success' : 'warning text-dark' }}">{{ $item['ready'] ? 'Ready' : 'Blocked' }}</span>
                                </div>
                                @if($item['ready'])
                                    <div class="text-muted small">This step is complete.</div>
                                @else
                                    <ul class="small text-muted mt-2 mb-3">
                                        @foreach($item['blockers'] as $blocker)
                                            <li>{{ $blocker }}</li>
                                        @endforeach
                                    </ul>
                                    @if(Route::has($item['route']))
                                        <a href="{{ route($item['route']) }}" class="btn btn-sm btn-outline-primary">Resolve</a>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
