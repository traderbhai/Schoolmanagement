@extends('layouts.admin')

@section('title', 'Dean Risk Governance')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Advanced Risk Governance</h1>
            <div class="small text-muted">Configurable thresholds, historical trends, owners, mitigation plans, and explainable risk.</div>
        </div>
        @include('academics.dean-os.partials.nav')
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>Overall thresholds: medium {{ $threshold->medium_threshold }}, high {{ $threshold->high_threshold }}, critical {{ $threshold->critical_threshold }}</div>
            <form method="POST" action="{{ route('academics.dean-os.risk-history.capture') }}">
                @csrf
                <button class="btn btn-sm btn-primary">Capture Snapshot</button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header py-2 fw-semibold">Risk History</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Dean risk history">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Program</th>
                                <th>Score</th>
                                <th>Band</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $row)
                                <tr>
                                    <td>{{ optional($row->snapshot_date)->format('d M Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row->program?->code ?? $row->program?->name ?? 'Unassigned program' }}</div>
                                        @if($row->program?->name && $row->program?->code !== $row->program?->name)
                                            <div class="small text-muted">{{ $row->program->name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row->score }}</td>
                                    <td><span class="badge text-bg-light border">{{ $row->band }}</span></td>
                                    <td>{{ str_replace('_', ' ', $row->trend) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No risk snapshots have been captured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-2">{{ $history->links() }}</div>
            </div>
        </div>

        <div class="col-lg-5">
            <form method="POST" action="{{ route('academics.dean-os.risk-mitigation.store') }}" class="card shadow-sm">
                @csrf
                <div class="card-header py-2 fw-semibold">Mitigation Plan</div>
                <div class="card-body vstack gap-2">
                    <textarea class="form-control form-control-sm" name="plan" placeholder="Mitigation plan" required></textarea>
                    <input class="form-control form-control-sm" type="date" name="due_at">
                    <button class="btn btn-sm btn-primary">Save Mitigation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
