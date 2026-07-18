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
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Risk governance sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Check thresholds</span>
            <span class="badge text-bg-light border">2. Capture snapshot</span>
            <span class="badge text-bg-light border">3. Review trend and band</span>
            <span class="badge text-bg-light border">4. Assign mitigation</span>
            <span class="badge text-bg-light border">5. Recheck after action closure</span>
        </div>
        <div class="text-muted mt-2">Risk is a governance trigger, not only a score. High and critical rows should have an owner, source reason, and mitigation plan.</div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>Overall thresholds: medium {{ $threshold->medium_threshold }}, high {{ $threshold->high_threshold }}, critical {{ $threshold->critical_threshold }}</div>
            <form method="POST" action="{{ route('academics.dean-os.risk-history.capture') }}">
                @csrf
                <button class="btn btn-sm btn-primary" onclick="return confirm('Capture or refresh today\\'s Dean risk snapshot?')">Capture Snapshot</button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header py-2"><div class="fw-semibold">Risk History</div><div class="small text-muted">Use trend movement to see whether program risk is improving, stable, or worsening.</div></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Dean risk history">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Program</th>
                                <th scope="col">Score</th>
                                <th scope="col">Band</th>
                                <th scope="col">Trend</th>
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
                <div class="card-header py-2"><div class="fw-semibold">Mitigation Plan</div><div class="small text-muted">Capture the practical intervention, target date, and follow-up owner for risk review.</div></div>
                <div class="card-body vstack gap-2">
                    <textarea aria-label="Mitigation plan" class="form-control form-control-sm" name="plan" placeholder="Mitigation plan" required></textarea>
                    <input aria-label="Due At" class="form-control form-control-sm" type="date" name="due_at">
                    <button class="btn btn-sm btn-primary" onclick="return confirm('Save this Dean risk mitigation plan? Confirm intervention owner, due date, affected program risk, and follow-up evidence before saving mitigation.')">Save Mitigation</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
