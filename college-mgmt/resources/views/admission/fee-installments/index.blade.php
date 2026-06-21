@extends('layouts.admin')
@section('title', 'Fee Installments - ' . $program->name)
@section('page-title', 'Fee Installments')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">{{ $program->name }}</h4>
            <span class="text-muted small">Fee installment configuration for applicant admission payment milestones</span>
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

    <div class="alert alert-info border-0 shadow-sm small">
        <div class="fw-semibold mb-1">Admission fee setup sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Select program</span>
            <span class="badge text-bg-light border">2. Choose batch scope</span>
            <span class="badge text-bg-light border">3. Add milestones</span>
            <span class="badge text-bg-light border">4. Publish active installments</span>
            <span class="badge text-bg-light border">5. Applicants pay from their portal</span>
        </div>
        <div class="text-muted mt-2">Installments linked to admission payments are protected from financial changes, so confirm amount, batch, and due date before collection begins.</div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Program</label>
                    <select class="form-select form-select-sm" onchange="window.location.href='/admission/fee-installments/'+this.value">
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
                @if($selectedBatchId)
                    <div class="col-md-4">
                        <a href="{{ route('admission.fee-installments.index', $program) }}" class="btn btn-outline-secondary btn-sm">
                            Clear batch filter
                        </a>
                    </div>
                @endif
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
                <div class="text-center text-muted py-5 px-3">
                    <i class="bi bi-cash-coin fs-1 d-block mb-2"></i>
                    <div class="fw-semibold text-dark mb-1">No admission fee installments are configured for this scope</div>
                    <p class="mb-3">
                        Add the registration/admission payment milestones that applicants must pay after selection or offer. If you selected a batch filter, clear it to review program-level installments.
                    </p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="{{ route('admission.fee-installments.create', $program) }}" class="btn btn-primary btn-sm">Add First Installment</a>
                        @if($selectedBatchId)
                            <a href="{{ route('admission.fee-installments.index', $program) }}" class="btn btn-outline-secondary btn-sm">Clear batch filter</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Batch</th>
                                <th class="text-end">Amount (Rs.)</th>
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
                                    <td>{{ $inst->batch?->name ?? 'All Batches' }}</td>
                                    <td class="text-end fw-semibold">Rs. {{ number_format($inst->amount, 2) }}</td>
                                    <td>{{ $inst->due_date?->format('d M Y') ?? 'Due date not published' }}</td>
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
                                <td class="text-end fw-bold">Rs. {{ number_format($installments->sum('amount'), 2) }}</td>
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
