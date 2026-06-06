@extends('layouts.admin')

@section('title', 'Waitlist Management — ' . $program->name)

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Waitlist Management</h4>
        <p class="text-muted mb-0 small">{{ $program->name }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        {{-- Program Switcher --}}
        <form method="GET" action="" class="d-flex gap-2 align-items-center" id="programForm">
            <select name="" class="form-select form-select-sm" style="min-width:180px"
                    onchange="this.form.action = '{{ url()->current().'' }}'; window.location = '{{ route('admission.waitlist.index', ['program'=>'__ID__']) }}'.replace('__ID__', this.value)">
                @foreach($programs as $prog)
                    <option value="{{ $prog->id }}" @selected($prog->id === $program->id)>{{ $prog->name }}</option>
                @endforeach
            </select>
        </form>

        {{-- Batch Filter --}}
        <form method="GET" action="{{ route('admission.waitlist.index', $program) }}" class="d-flex gap-2 align-items-center">
            <select name="batch_id" class="form-select form-select-sm" style="min-width:140px" onchange="this.form.submit()">
                <option value="">All Batches</option>
                @foreach($batches as $batch)
                    <option value="{{ $batch->id }}" @selected((string)$batch->id === (string)$batchId)>{{ $batch->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

{{-- Seat Capacity Banner --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">
                    {{ $totalSeats > 0 ? $totalSeats : '—' }}
                </div>
                <div class="small text-muted"><i class="bi bi-grid me-1"></i>Total Seats
                    @if($totalSeats === 0)
                        <span class="text-warning d-block">Not configured</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">{{ $selectedCount }}</div>
                <div class="small text-muted"><i class="bi bi-check-circle me-1"></i>Selected</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold {{ $availableSeats > 0 ? 'text-success' : 'text-danger' }}">
                    {{ $availableSeats }}
                </div>
                <div class="small text-muted">
                    <i class="bi bi-{{ $availableSeats > 0 ? 'unlock' : 'lock' }} me-1"></i>Available Seats
                    <span class="badge {{ $availableSeats > 0 ? 'bg-success' : 'bg-danger' }} ms-1">
                        {{ $availableSeats > 0 ? 'Open' : 'Full' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Waitlist Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-list-ol me-2 text-warning"></i>Waitlisted Candidates</span>
        <span class="badge bg-secondary">{{ $waitlisted->count() }} candidate(s)</span>
    </div>

    @if($waitlisted->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">Rank</th>
                    <th>Applicant Name</th>
                    <th>Application No</th>
                    <th>Batch</th>
                    <th>Merit Score</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($waitlisted as $entry)
                <tr>
                    <td>
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning text-dark fw-bold"
                              style="width:36px;height:36px;font-size:.9rem">
                            {{ $entry->rank }}
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $entry->applicant->user->name ?? 'N/A' }}</div>
                        <div class="small text-muted">{{ $entry->applicant->user->email ?? '' }}</div>
                    </td>
                    <td>
                        <code class="small">{{ $entry->applicant->application_number ?? '—' }}</code>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">{{ $entry->applicant->batch->name ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold">{{ number_format($entry->total_weighted_score, 2) }}</span>
                    </td>
                    <td class="text-end">
                        @if($availableSeats > 0)
                            <form method="POST" action="{{ route('admission.waitlist.promote', $entry) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Promote {{ $entry->applicant->user->name ?? 'this candidate' }} from waitlist to selected?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-arrow-up-circle me-1"></i> Promote
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                <i class="bi bi-lock me-1"></i> No Seats
                            </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-hourglass fs-2 d-block mb-2"></i>
        <p class="mb-0">No waitlisted candidates for this program.</p>
        @if($batchId)
            <a href="{{ route('admission.waitlist.index', $program) }}" class="btn btn-outline-secondary btn-sm mt-3">
                <i class="bi bi-x me-1"></i> Clear Batch Filter
            </a>
        @endif
    </div>
    @endif

    <div class="card-footer bg-transparent text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Candidates are listed in merit order. Promotions are seat-capacity checked against the seat matrix.
    </div>
</div>
@endsection
