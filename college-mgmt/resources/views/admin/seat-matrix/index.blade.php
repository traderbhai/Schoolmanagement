@extends('layouts.admin')
@section('title', 'Seat Matrix — ' . $program->name)
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admin.programs.show', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> {{ $program->name }}</a>
        <h2 class="fw-bold mb-0 mt-1"><i class="bi bi-grid-3x3-gap me-2"></i>Seat Matrix</h2>
    </div>
    <a href="{{ route('admission.seat-matrices.create', $program) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Add Seat Matrix
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@forelse($matrices as $matrix)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $program->name }}</strong>
            @if($matrix->batch)
                <span class="badge bg-info text-white ms-2">{{ $matrix->batch->name }}</span>
            @else
                <span class="badge bg-secondary ms-2">All Batches (Default)</span>
            @endif
            @if(!$matrix->is_active)
                <span class="badge bg-warning text-dark ms-1">Inactive</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admission.seat-matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <form method="POST" action="{{ route('admission.seat-matrices.destroy', $matrix) }}" onsubmit="return confirm('Delete this seat matrix?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center">
            @php
                $cats = [
                    'General' => $matrix->general_seats,
                    'OBC' => $matrix->obc_seats,
                    'OBC-NC' => $matrix->obc_nc_seats,
                    'SC' => $matrix->sc_seats,
                    'ST' => $matrix->st_seats,
                    'EWS' => $matrix->ews_seats,
                    'PWD*' => $matrix->pwd_seats,
                    'NRI*' => $matrix->nri_seats,
                    'Mgmt Quota' => $matrix->management_quota_seats,
                ];
            @endphp
            @foreach($cats as $label => $seats)
            <div class="col-6 col-sm-4 col-md-2 col-lg-1">
                <div class="border rounded p-2">
                    <div class="fw-bold fs-5 text-primary">{{ $seats }}</div>
                    <div class="small text-muted">{{ $label }}</div>
                </div>
            </div>
            @endforeach
            <div class="col-6 col-sm-4 col-md-2 col-lg-1">
                <div class="border rounded p-2 bg-light">
                    <div class="fw-bold fs-5">{{ $matrix->total_seats }}</div>
                    <div class="small text-muted">Total</div>
                </div>
            </div>
        </div>
        @if($matrix->state_quota_percentage > 0)
        <div class="mt-2 text-muted small">State Quota: {{ $matrix->state_quota_percentage }}%</div>
        @endif
        <div class="mt-1 text-muted" style="font-size:0.75rem">* Supernumerary (not counted in total intake)</div>
    </div>
</div>
@empty
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-grid-3x3-gap fs-1 text-muted"></i>
        <p class="mt-3 text-muted">No seat matrix configured yet.</p>
        <a href="{{ route('admission.seat-matrices.create', $program) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Create Seat Matrix
        </a>
    </div>
</div>
@endforelse
@endsection
