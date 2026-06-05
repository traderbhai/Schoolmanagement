@extends('layouts.admin')
@section('title', 'Fee Management')
@section('page-title', 'Fee Management')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Fee Management</h5>
        <div class="text-muted" style="font-size:.82rem">Manage fee structures and payments</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.fees.collect') }}" class="btn btn-success">
            <i class="bi bi-cash me-1"></i>Collect Payment
        </a>
        <a href="{{ route('admin.fees.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Fee Structure
        </a>
    </div>
</div>

{{-- Summary Stats --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="kpi-card kpi-blue">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Fee Structures</div>
                    <div class="kpi-value mt-1">{{ $structures->total() }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-green">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Total Defined Amount</div>
                    <div class="kpi-value mt-1">₹{{ number_format($structures->sum('amount')) }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="kpi-card kpi-amber">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div class="kpi-label">Courses Covered</div>
                    <div class="kpi-value mt-1">{{ $structures->pluck('course_id')->unique()->count() }}</div>
                </div>
                <div class="kpi-icon"><i class="bi bi-journal-bookmark-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Fee Structures</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Course</th>
                    <th>Academic Year</th>
                    <th>Fee Type</th>
                    <th>Semester</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($structures as $f)
            <tr>
                <td class="text-muted">{{ $structures->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $f->course->name }}</td>
                <td>{{ $f->academicYear->name }}</td>
                <td><span class="badge bg-light text-dark border">{{ $f->fee_type }}</span></td>
                <td>{{ $f->semester_number ? 'Sem '.$f->semester_number : 'All' }}</td>
                <td class="fw-semibold" style="color:#059669">₹{{ number_format($f->amount, 2) }}</td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.fees.show', $f) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.fees.edit', $f) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.fees.destroy', $f) }}"
                            data-name="{{ $f->fee_type }} – {{ $f->course->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="mt-2 fw-semibold">No fee structures defined yet</div>
                        <a href="{{ route('admin.fees.create') }}" class="btn btn-primary btn-sm mt-3">Add Fee Structure</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($structures->hasPages())
    <div class="card-footer">{{ $structures->links() }}</div>
    @endif
</div>
@endsection
