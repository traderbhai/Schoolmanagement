@extends('layouts.admin')
@section('title','Academic Years')
@section('page-title','Academic Years')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.academic-years.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Year</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Year</th><th>Start Date</th><th>End Date</th><th>Semesters</th><th>Current</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($years as $y)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $y->name }}</td>
                <td>{{ $y->start_year }}–{{ $y->end_year }}</td>
                <td>{{ $y->start_date->format('d M Y') }}</td>
                <td>{{ $y->end_date->format('d M Y') }}</td>
                <td>{{ $y->semesters_count }}</td>
                <td>@if($y->is_current)<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Current</span>@else<span class="text-muted small">–</span>@endif</td>
                <td>
                    <a href="{{ route('admin.academic-years.show', $y) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.academic-years.edit', $y) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.academic-years.destroy', $y) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($years->hasPages())<div class="card-footer">{{ $years->links() }}</div>@endif
</div>
@endsection
