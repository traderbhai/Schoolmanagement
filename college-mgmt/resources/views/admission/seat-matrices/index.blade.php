@extends('layouts.admin')
@section('title', 'Seat Matrix - ' . $program->name)
@section('page-title', 'Seat Matrix Configuration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">{{ $program->name }}</h4>
            <span class="text-muted small">Category-wise intake configuration for offers, waitlist movement, and enrollment seat control</span>
        </div>
        <a href="{{ route('admission.seat-matrices.create', $program) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Seat Matrix
        </a>
    </div>

    <div class="alert alert-info border-0 shadow-sm small">
        <div class="fw-semibold mb-1">Seat-control setup sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Select program</span>
            <span class="badge text-bg-light border">2. Choose batch scope</span>
            <span class="badge text-bg-light border">3. Enter category seats</span>
            <span class="badge text-bg-light border">4. Publish offers and waitlist</span>
            <span class="badge text-bg-light border">5. Monitor enrollment usage</span>
        </div>
        <div class="text-muted mt-2">Seat matrices with selections, offers, waitlist movement, or enrollment history are protected from deletion and cannot be reduced below committed applicants.</div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <label class="form-label small text-muted">Program</label>
            <select aria-label="Seat matrix program" class="form-select form-select-sm" style="width:auto" onchange="window.location='/admission/seat-matrices/'+this.value">
                @foreach($programs as $p)
                    <option value="{{ $p->id }}" {{ $p->id == $program->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if($matrices->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5 px-3">
                <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>
                <div class="fw-semibold text-dark mb-1">No seat matrix is configured for this program yet</div>
                <p class="mb-3">
                    Configure total, reservation, and quota seats before offer rounds, waitlist promotions, or manual seat holds are published for this program.
                </p>
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
                                <th scope="col" class="text-start">Batch Scope</th>
                                <th scope="col">Total</th>
                                <th scope="col">General</th>
                                <th scope="col">OBC</th>
                                <th scope="col">SC</th>
                                <th scope="col">ST</th>
                                <th scope="col">EWS</th>
                                <th scope="col">Mgmt</th>
                                <th scope="col">NRI</th>
                                <th scope="col">Defence</th>
                                <th aria-label="Actions" scope="col"></th>
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
                                        <a href="{{ route('admission.seat-matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-secondary py-0 px-1" aria-label="Edit seat matrix for {{ $matrix->batch?->name ?? 'all batches' }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
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
