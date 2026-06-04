@extends('layouts.admin')
@section('title','Fee Management')
@section('page-title','Fee Management')
@section('content')
<div class="d-flex justify-content-end gap-2 mb-3">
    <a href="{{ route('admin.fees.collect') }}" class="btn btn-success btn-sm"><i class="bi bi-cash me-1"></i>Collect Payment</a>
    <a href="{{ route('admin.fees.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Fee Structure</a>
</div>
<div class="card">
    <div class="card-header">Fee Structures</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Course</th><th>Academic Year</th><th>Fee Type</th><th>Semester</th><th>Amount</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($structures as $f)
            <tr>
                <td>{{ $structures->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $f->course->name }}</td>
                <td>{{ $f->academicYear->name }}</td>
                <td>{{ $f->fee_type }}</td>
                <td>{{ $f->semester_number ? 'Sem '.$f->semester_number : 'All' }}</td>
                <td class="fw-semibold text-success">₹{{ number_format($f->amount, 2) }}</td>
                <td>
                    <a href="{{ route('admin.fees.show', $f) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.fees.edit', $f) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.fees.destroy', $f) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No fee structures defined.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($structures->hasPages())<div class="card-footer">{{ $structures->links() }}</div>@endif
</div>
@endsection
