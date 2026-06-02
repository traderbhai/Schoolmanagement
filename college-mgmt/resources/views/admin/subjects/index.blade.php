@extends('layouts.admin')
@section('title','Subjects')
@section('page-title','Subjects')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.subjects.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add Subject</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Department</th><th>Type</th><th>Credits</th><th>Hrs/Wk</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($subjects as $s)
            <tr>
                <td>{{ $subjects->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $s->name }}</td>
                <td><code>{{ $s->code }}</code></td>
                <td>{{ $s->department->code }}</td>
                <td><span class="badge {{ match($s->type){'theory'=>'bg-primary','practical'=>'bg-warning text-dark','tutorial'=>'bg-info text-dark',default=>'bg-secondary'} }}">{{ ucfirst($s->type) }}</span></td>
                <td>{{ $s->credits }}</td>
                <td>{{ $s->hours_per_week }}</td>
                <td><span class="badge {{ $s->is_active ? 'bg-success' : 'bg-danger' }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td>
                    <a href="{{ route('admin.subjects.edit', $s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('admin.subjects.destroy', $s) }}" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Del</button></form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($subjects->hasPages())<div class="card-footer">{{ $subjects->links() }}</div>@endif
</div>
@endsection
