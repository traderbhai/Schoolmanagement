@extends('layouts.admin')
@section('title','Students')
@section('page-title','Students')
@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2" method="GET">
            <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, email, enrollment..." value="{{ request('search') }}"></div>
            <div class="col-md-2">
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)<option value="{{ $c->id }}" @selected(request('course_id')==$c->id)>{{ $c->code }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['active','inactive','graduated','dropped'] as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Search</button></div>
            <div class="col-auto ms-auto"><a href="{{ route('admin.students.create') }}" class="btn btn-success btn-sm"><i class="bi bi-person-plus me-1"></i>Add Student</a></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Name</th><th>Enrollment No.</th><th>Course</th><th>Semester</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($students as $s)
            <tr>
                <td>{{ $students->firstItem() + $loop->index }}</td>
                <td>
                    <a href="{{ route('admin.students.show', $s) }}" class="text-decoration-none fw-semibold">{{ $s->user->name }}</a>
                    <div class="text-muted small">{{ $s->user->email }}</div>
                </td>
                <td><code>{{ $s->enrollment_number }}</code></td>
                <td>{{ $s->course->code }}</td>
                <td>Sem {{ $s->current_semester }}</td>
                <td><span class="badge {{ match($s->status){
                    'active'=>'bg-success','inactive'=>'bg-secondary','graduated'=>'bg-primary','dropped'=>'bg-danger',default=>'bg-secondary'
                } }}">{{ ucfirst($s->status) }}</span></td>
                <td>
                    <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-outline-info">View</a>
                    <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No students found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($students->hasPages())
    <div class="card-footer">{{ $students->links() }}</div>
    @endif
</div>
@endsection
