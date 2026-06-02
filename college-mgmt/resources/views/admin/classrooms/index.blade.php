@extends('layouts.admin')
@section('title','Classrooms')
@section('page-title','Classrooms')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.classrooms.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Room</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Room No.</th><th>Type</th><th>Capacity</th><th>Building</th><th>Features</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($classrooms as $r)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="fw-semibold">{{ $r->name }}</td>
                <td><span class="badge bg-secondary">{{ $r->room_number }}</span></td>
                <td>{{ ucfirst($r->type) }}</td>
                <td>{{ $r->capacity }}</td>
                <td>{{ $r->building ?? '–' }}</td>
                <td>
                    @if($r->has_projector)<span class="badge bg-light text-dark border me-1"><i class="bi bi-projector"></i> Projector</span>@endif
                    @if($r->has_lab)<span class="badge bg-light text-dark border"><i class="bi bi-pc-display"></i> Lab</span>@endif
                </td>
                <td><span class="badge {{ $r->is_active ? 'bg-success' : 'bg-danger' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <a href="{{ route('admin.classrooms.show', $r) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.classrooms.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.classrooms.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($classrooms->hasPages())<div class="card-footer">{{ $classrooms->links() }}</div>@endif
</div>
@endsection
