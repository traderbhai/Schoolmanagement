@extends('layouts.admin')
@section('title', 'Seat Matrix — ' . $program->name)
@section('page-title', 'Seat Matrix Configuration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">{{ $program->name }}</h4>
            <span class="text-muted small">Seat Matrix — Category-wise Intake Configuration</span>
        </div>
        <a href="{{ route('admission.seat-matrices.create', $program) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Seat Matrix
        </a>
    </div>

    <div class="mb-3">
        <select class="form-select form-select-sm" style="width:auto" onchange="window.location='/admission/seat-matrices/'+this.value">
            @foreach($programs as $p)
                <option value="{{ $p->id }}" {{ $p->id == $program->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($matrices->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>
                <p>No seat matrix configured yet.</p>
                <a href="{{ route('admission.seat-matrices.create', $program) }}" class="btn btn-primary btn-sm">Configure Seats</a>
            </div>
        </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Batch</th>
                            <th>Total</th>
                            <th>General</th>
                            <th>OBC</th>
                            <th>SC</th>
                            <th>ST</th>
                            <th>EWS</th>
                            <th>Mgmt</th>
                            <th>NRI</th>
                            <th>Defence</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($matrices as $matrix)
                        <tr>
                            <td class="text-start fw-semibold">{{ $matrix->batch?->name ?? 'All Batches' }}</td>
                            <td class="fw-bold text-primary">{{ $matrix->total_seats }}</td>
                            <td>{{ $matrix->general_seats }}</td>
                            <td>{{ $matrix->obc_seats }}</td>
                            <td>{{ $matrix->sc_seats }}</td>
                            <td>{{ $matrix->st_seats }}</td>
                            <td>{{ $matrix->ews_seats }}</td>
                            <td>{{ $matrix->management_quota }}</td>
                            <td>{{ $matrix->nri_quota }}</td>
                            <td>{{ $matrix->defence_quota }}</td>
                            <td>
                                <a href="{{ route('admission.seat-matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
