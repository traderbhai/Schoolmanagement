@extends('layouts.admin')

@section('title', 'Waitlist Management - ' . $program->name)

@section('content')
@php
    $selectedBatch = $batchId ? $batches->firstWhere('id', (int) $batchId) : null;
    $filterSummary = $selectedBatch
        ? 'Program: ' . $program->name . ' | Batch: ' . $selectedBatch->name
        : 'Program: ' . $program->name . ' | All batches';
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Waitlist Management</h4>
        <p class="text-muted mb-0 small">{{ $filterSummary }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <form method="GET" action="" class="d-flex gap-2 align-items-center" id="programForm">
            <label class="visually-hidden" for="waitlist_program_switcher">Program</label>
            <select id="waitlist_program_switcher" name="program_id" class="form-select form-select-sm" style="min-width:180px"
                    onchange="window.location = '{{ route('admission.waitlist.index', ['program'=>'__ID__']) }}'.replace('__ID__', this.value)">
                @foreach($programs as $prog)
                    <option value="{{ $prog->id }}" @selected($prog->id === $program->id)>{{ $prog->name }}</option>
                @endforeach
            </select>
        </form>

        <form method="GET" action="{{ route('admission.waitlist.index', $program) }}" class="d-flex gap-2 align-items-center">
            <label class="visually-hidden" for="waitlist_batch_filter">Batch</label>
            <select id="waitlist_batch_filter" name="batch_id" class="form-select form-select-sm" style="min-width:140px" onchange="this.form.submit()">
                <option value="">All Batches</option>
                @foreach($batches as $batch)
                    <option value="{{ $batch->id }}" @selected((string)$batch->id === (string)$batchId)>{{ $batch->name }}</option>
                @endforeach
            </select>
            @if($batchId)
                <a href="{{ route('admission.waitlist.index', $program) }}" class="btn btn-sm btn-outline-secondary">Clear Filter</a>
            @endif
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">
                    {{ $totalSeats > 0 ? $totalSeats : 'Not configured' }}
                </div>
                <div class="small text-muted"><i class="bi bi-grid me-1"></i>Total Seats</div>
                @if($totalSeats === 0)
                    <div class="small text-warning mt-1">Configure a seat matrix before promotions can proceed.</div>
                @endif
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span class="fw-bold"><i class="bi bi-list-ol me-2 text-warning"></i>Waitlisted Candidates</span>
            <div class="small text-muted">Filter: {{ $filterSummary }}</div>
        </div>
        <span class="badge bg-secondary">{{ $waitlisted->count() }} candidate(s)</span>
    </div>

    @if($waitlisted->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="visually-hidden">Waitlisted applicants in merit order for seat promotion</caption>
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
                                <div class="fw-semibold">{{ $entry->applicant->user->name ?? 'Applicant name missing' }}</div>
                                <div class="small text-muted">{{ $entry->applicant->user->email ?? 'Email not provided' }}</div>
                            </td>
                            <td>
                                <code class="small">{{ $entry->applicant->application_number ?? 'Application number missing' }}</code>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $entry->applicant->batch->name ?? 'Batch not assigned' }}</span>
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
        <div class="card-body text-center text-muted py-5 px-3">
            <i class="bi bi-hourglass fs-2 d-block mb-2"></i>
            <h5 class="text-body mb-2">No waitlisted candidates are visible</h5>
            <p class="mb-3">
                Waitlist rows appear after merit-list decisions mark applicants as waitlisted for this program
                @if($selectedBatch)
                    and selected batch
                @endif
                . If the list should contain candidates, check merit-list decisions, selected batch filters, and seat matrix setup.
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                @if($batchId)
                    <a href="{{ route('admission.waitlist.index', $program) }}" class="btn btn-outline-secondary btn-sm">
                        Clear Batch Filter
                    </a>
                @endif
                <a href="{{ route('admission.merit-list.index', $program) }}" class="btn btn-outline-primary btn-sm">Open Merit List</a>
                <a href="{{ route('admission.seat-matrices.index', $program) }}" class="btn btn-outline-primary btn-sm">Review Seat Matrix</a>
            </div>
        </div>
    @endif

    <div class="card-footer bg-transparent text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Candidates are listed in merit order. Promotions are seat-capacity checked against the active seat matrix before status changes are saved.
    </div>
</div>
@endsection
