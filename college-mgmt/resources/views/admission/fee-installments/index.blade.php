@extends('layouts.admin')
@section('title', 'Fee Installments — ' . $program->name)
@section('page-title', 'Fee Installments')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">{{ $program->name }}</h4>
            <span class="text-muted small">Fee Installment Configuration</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admission.fee-installments.duplicate-form', $program) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-files me-1"></i>Duplicate from Batch
            </a>
            <a href="{{ route('admission.fee-installments.create', $program) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add Installment
            </a>
        </div>
    </div>

    {{-- Program Selector --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Program</label>
                    <select name="" class="form-select form-select-sm" onchange="window.location.href='/admission/fee-installments/'+this.value">
                        @foreach($programs as $p)
                            <option value="{{ $p->id }}" {{ $p->id == $program->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">Filter by Batch</label>
                    <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Batches</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" {{ $selectedBatchId == $batch->id ? 'selected' : '' }}>{{ $batch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($installments->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                    <p>No installments configured yet.</p>
                    <a href="{{ route('admission.fee-installments.create', $program) }}" class="btn btn-primary btn-sm">Add First Installment</a>
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Batch</th>
                            <th class="text-end">Amount (₹)</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Payments</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($installments as $inst)
                        <tr>
                            <td class="fw-semibold">{{ $inst->installment_number }}</td>
                            <td>
                                <div class="fw-semibold">{{ $inst->name }}</div>
                                @if($inst->description)
                                    <div class="text-muted small">{{ Str::limit($inst->description, 50) }}</div>
                                @endif
                            </td>
                            <td>{!! $inst->batch?->name ?? '<span class="text-muted">All Batches</span>' !!}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($inst->amount, 2) }}</td>
                            <td>{{ $inst->due_date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                @if($inst->is_active)
                                    <span class="badge bg-success-subtle text-success">Active</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $inst->payments->count() }}</td>
                            <td>
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('admission.fee-installments.edit', $inst) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    @if($inst->payments->count() === 0)
                                    <form action="{{ route('admission.fee-installments.destroy', $inst) }}" method="POST" onsubmit="return confirm('Delete this installment?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-semibold">Total</td>
                            <td class="text-end fw-bold">₹{{ number_format($installments->sum('amount'), 2) }}</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
