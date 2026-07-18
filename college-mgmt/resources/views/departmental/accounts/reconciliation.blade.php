@extends('layouts.admin')

@section('title', 'Admission Fee Reconciliation')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div>
        <h2 class="fw-bold mb-0">Admission Fee Reconciliation</h2>
        <p class="text-muted mb-0">Verified admission payments grouped by program.</p>
    </div>
    <a href="{{ route('accounts.export-admission-payments', request()->only('program_id')) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i>Export Current View
    </a>
</div>

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Reconciliation workflow</div>
            <div class="small text-muted">Use this page after payment verification to compare verified Admission receipts by program and export the exact filtered reconciliation list.</div>
            <div class="small text-muted mt-1">
                <span class="badge text-bg-light me-1">Owner: Accounts office</span>
                <span class="badge text-bg-light">Source: verified Admission payment records</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Filter program</span>
            <span class="badge text-bg-light">2. Review verified receipts</span>
            <span class="badge text-bg-light">3. Compare totals</span>
            <span class="badge text-bg-light">4. Export current view</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($summaryByProgram as $row)
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="small text-muted fw-semibold mb-1">{{ $row->program_name }}</div>
                <div class="fs-4 fw-bold text-success">Rs. {{ number_format($row->total_collected, 0) }}</div>
                <div class="small text-muted">{{ $row->payment_count }} verified payments</div>
                <div class="mt-2">
                    <a href="{{ route('accounts.reconciliation', ['program_id' => $row->program_id]) }}"
                       class="btn btn-sm btn-outline-primary">Filter</a>
                </div>
            </div>
        </div>
    </div>
    @endforeach
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body">
                <div class="small fw-semibold mb-1 opacity-75">Grand Total</div>
                <div class="fs-4 fw-bold">Rs. {{ number_format($grandTotal, 0) }}</div>
                <div class="small opacity-75">All verified admission payments</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div>
                <div class="fw-semibold">Filtered Source List ({{ $payments->total() }})</div>
                <div class="small text-muted">Visible filter summary: {{ $selectedProgram ? 'Program: ' . $selectedProgram->name : 'Showing all verified Admission payments.' }}</div>
            </div>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Filter by Program</label>
                <select aria-label="Program" name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    @foreach($programs as $prog)
                        <option value="{{ $prog->id }}" {{ request('program_id') == $prog->id ? 'selected' : '' }}>
                            {{ $prog->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(request('program_id'))
                <a href="{{ route('accounts.reconciliation') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        @if($payments->isEmpty())
            <div class="text-center py-5 text-muted">
                No verified admission payments match this reconciliation view. Check the program filter or verify pending Admission payments before reconciling.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th scope="col">Applicant</th>
                        <th scope="col">Application No.</th>
                        <th scope="col">Program</th>
                        <th scope="col">Owner / Source</th>
                        <th scope="col">Reference No.</th>
                        <th scope="col">Method</th>
                        <th scope="col" class="text-end">Amount (Rs.)</th>
                        <th scope="col">Verified At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $pmt)
                    <tr>
                        <td class="fw-semibold">{{ $pmt->applicant->user->name ?? 'Applicant not linked' }}</td>
                        <td class="font-monospace small">{{ $pmt->applicant->application_number ?? 'Application number missing' }}</td>
                        <td>{{ $pmt->applicant->program->name ?? 'Program not assigned' }}</td>
                        <td>
                            <div class="small text-muted">Owner: Accounts office</div>
                            <div class="small text-muted">Source: Verified Admission payment</div>
                        </td>
                        <td class="font-monospace small">{{ $pmt->reference_number ?? 'Reference not recorded' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $pmt->payment_method ?? 'Method not recorded')) }}</td>
                        <td class="text-end fw-semibold text-success">Rs. {{ number_format($pmt->amount_paid, 2) }}</td>
                        <td class="small text-muted">{{ $pmt->verified_at?->format('d M Y, h:i A') ?? 'Verification time missing' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $payments->render() }}</div>
        @endif
    </div>
</div>
@endsection
