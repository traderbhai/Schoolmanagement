@extends('layouts.admin')
@section('title','Semesters')
@section('page-title','Semesters')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.semesters.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Semester</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Academic Year</th><th>Number</th><th>Start</th><th>End</th><th>Current</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($semesters as $s)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td>{{ $s->academicYear->name }}</td>
                <td>Sem {{ $s->number }}</td>
                <td>{{ $s->start_date->format('d M Y') }}</td>
                <td>{{ $s->end_date->format('d M Y') }}</td>
                <td>@if($s->is_current)<span class="badge bg-success">Current</span>@endif</td>
                <td>
                    <a href="{{ route('admin.semesters.edit', $s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.semesters.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($semesters->hasPages())<div class="card-footer">{{ $semesters->links() }}</div>@endif
</div>
@endsection
