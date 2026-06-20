@extends('layouts.admission-partner')

@section('title', 'Admission Partner Dashboard')
@section('page-title', 'Admission Partner Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">{{ $partner->name }}</h2>
            <div class="text-muted small">Partner status: <span class="badge text-bg-{{ $partner->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($partner->status) }}</span></div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.partner-portal.leads') }}">
            <i class="bi bi-list-check me-1"></i> View submitted leads
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="{{ route('admission.partner-portal.leads') }}"><div class="card-body py-3"><div class="text-muted small">Total leads</div><div class="h4 mb-0">{{ $summary['leads'] }}</div></div></a></div>
        <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="{{ route('admission.partner-portal.leads', ['status' => 'converted']) }}"><div class="card-body py-3"><div class="text-muted small">Converted</div><div class="h4 mb-0">{{ $summary['converted'] }}</div></div></a></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body py-3"><div class="text-muted small">Conversion</div><div class="h4 mb-0">{{ $summary['conversion_pct'] }}%</div></div></div></div>
        <div class="col-6 col-lg-3"><a class="card text-decoration-none h-100" href="{{ route('admission.partner-portal.leads', ['status' => 'not_interested']) }}"><div class="card-body py-3"><div class="text-muted small">Rejected/Lost</div><div class="h4 mb-0">{{ $summary['duplicates_or_rejected'] }}</div></div></a></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.partner-portal.leads.store') }}" class="card">
                @csrf
                <div class="card-header fw-semibold">Submit lead</div>
                <div class="card-body vstack gap-2">
                    <input class="form-control form-control-sm" name="name" placeholder="Candidate name" required>
                    <input class="form-control form-control-sm" name="email" placeholder="Email">
                    <input class="form-control form-control-sm" name="phone" placeholder="Phone">
                    <select class="form-select form-select-sm" name="program_id" required>
                        <option value="">Select program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}">{{ $program->name }}</option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm" name="partner_reference" placeholder="Partner reference">
                    <select class="form-select form-select-sm" name="priority">
                        <option value="normal">Normal priority</option>
                        <option value="high">High priority</option>
                        <option value="urgent">Urgent priority</option>
                        <option value="low">Low priority</option>
                    </select>
                    <textarea class="form-control form-control-sm" name="notes" rows="3" placeholder="Counselling notes"></textarea>
                    <button class="btn btn-primary btn-sm">Submit to admission team</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Latest submitted leads</span>
                    <span class="small text-muted">Status summary: {{ $statusCounts->map(fn ($total, $status) => "{$status}: {$total}")->implode(', ') ?: 'No leads yet' }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <caption class="visually-hidden">Latest partner leads</caption>
                        <thead><tr><th>Lead</th><th>Program</th><th>Status</th><th>Priority</th><th>Reference</th></tr></thead>
                        <tbody>
                            @forelse($latestLeads as $lead)
                                <tr>
                                    <td><div class="fw-semibold">{{ $lead->name }}</div><div class="small text-muted">{{ $lead->email ?: $lead->phone }}</div></td>
                                    <td>{{ $lead->program?->name ?? 'Not selected' }}</td>
                                    <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $lead->status) }}</span></td>
                                    <td>{{ ucfirst($lead->priority ?? 'normal') }}</td>
                                    <td>{{ $lead->partner_reference ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No partner leads submitted yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
