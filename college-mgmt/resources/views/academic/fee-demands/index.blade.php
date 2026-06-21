@extends('layouts.admin')

@section('title', 'Fee Demands')
@section('page-title', 'Fee Demands')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Generate Demands Panel --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">Generate Semester Fee Demands</div>
        <div class="card-body">
            <form method="POST" action="{{ route('academic.fee-demands.generate') }}">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label small">Batch</label>
                        <select name="batch_id" class="form-select form-select-sm" required>
                            <option value="">Select Batch</option>
                            @foreach($batches ?? [] as $b)
                            <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->program->name ?? 'Program not linked' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small">Term</label>
                        <select name="term_id" class="form-select form-select-sm" required>
                            <option value="">Select Term</option>
                            @foreach($terms ?? [] as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm('Generate fee demands for all active students in this batch/term?')">
                            <i class="bi bi-lightning me-1"></i>Generate Demands
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Apply Penalties Panel --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">Late Fee / Penalty</div>
        <div class="card-body d-flex align-items-center gap-3">
            <p class="mb-0 text-muted small">Apply a 2% per-month penalty to all overdue pending demands that have not yet been penalised.</p>
            <form method="POST" action="{{ route('academic.fee-demands.apply-penalties') }}" class="ms-auto">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm"
                    onclick="return confirm('Apply penalties to all overdue demands?')">
                    <i class="bi bi-exclamation-triangle me-1"></i>Apply Penalties
                </button>
            </form>
        </div>
    </div>

    <div class="mb-3">
        <a href="{{ route('academic.fee-demands.create') }}" class="btn btn-primary">Add Fee Demand</a>
    </div>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Term</th>
                        <th>Total</th>
                        <th>Final Amount</th>
                        <th>Penalty</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feeDemands as $demand)
                    <tr>
                        <td>{{ $demand->student->enrollment_number ?? 'Enrollment number pending' }}</td>
                        <td>{{ $demand->term->name ?? 'Term not linked' }}</td>
                        <td>Rs. {{ number_format($demand->total_amount) }}</td>
                        <td>Rs. {{ number_format($demand->final_amount) }}</td>
                        <td>
                            @if(($demand->penalty_amount ?? 0) > 0)
                                <span class="text-danger fw-semibold">Rs. {{ number_format($demand->penalty_amount, 2) }}</span>
                            @else
                                <span class="text-muted">No penalty</span>
                            @endif
                        </td>
                        <td class="{{ $demand->due_date && $demand->due_date->isPast() && $demand->status === 'pending' ? 'text-danger fw-semibold' : '' }}">
                            {{ $demand->due_date ? $demand->due_date->format('d M Y') : 'Due date not published' }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $demand->status === 'fully_paid' ? 'success' : ($demand->due_date && $demand->due_date->isPast() && $demand->status === 'pending' ? 'danger' : 'warning') }}">
                                {{ ucfirst(str_replace('_',' ',$demand->status)) }}
                            </span>
                        </td>
                        <td><a href="{{ route('academic.fee-demands.show', $demand) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            No fee demands match this view yet. Generate semester demands after batch, term, fee structure, and active student records are ready, or add a one-off demand for an individual student.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $feeDemands->links() }}
        </div>
    </div>
</div>
@endsection
