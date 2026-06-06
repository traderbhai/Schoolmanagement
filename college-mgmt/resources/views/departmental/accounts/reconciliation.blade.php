@extends('layouts.admin')

@section('title', 'Admission Fee Reconciliation')

@section('content')
<div class="mb-4">
    <h2 class="fw-bold mb-0">Admission Fee Reconciliation</h2>
    <p class="text-muted mb-0">Verified admission payments grouped by program</p>
</div>

{{-- Program Summary Cards --}}
<div class="row g-3 mb-4">
    @foreach($summaryByProgram as $row)
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="small text-muted fw-semibold mb-1">{{ $row->program_name }}</div>
                <div class="fs-4 fw-bold text-success">₹{{ number_format($row->total_collected, 0) }}</div>
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
                <div class="fs-4 fw-bold">₹{{ number_format($grandTotal, 0) }}</div>
                <div class="small opacity-75">All verified admission payments</div>
            </div>
        </div>
    </div>
</div>

{{-- Filter + Table --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <form method="GET" class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Filter by Program</label>
                <select name="program_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All Programs —</option>
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
            <div class="text-center py-5 text-muted">No verified payments found.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Application No.</th>
                        <th>Program</th>
                        <th>Reference No.</th>
                        <th>Method</th>
                        <th class="text-end">Amount (₹)</th>
                        <th>Verified At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $pmt)
                    <tr>
                        <td class="fw-semibold">{{ $pmt->applicant->user->name ?? '—' }}</td>
                        <td class="font-monospace small">{{ $pmt->applicant->application_number }}</td>
                        <td>{{ $pmt->applicant->program->name ?? '—' }}</td>
                        <td class="font-monospace small">{{ $pmt->reference_number ?? '—' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $pmt->payment_method ?? '—')) }}</td>
                        <td class="text-end fw-semibold text-success">₹{{ number_format($pmt->amount_paid, 2) }}</td>
                        <td class="small text-muted">{{ $pmt->verified_at?->format('d M Y, h:i A') }}</td>
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
