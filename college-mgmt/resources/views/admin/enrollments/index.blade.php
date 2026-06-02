@extends('layouts.admin')
@section('title','Enrollments')
@section('page-title','Subject Enrollments')
@section('content')
<div class="d-flex gap-2 justify-content-end mb-3">
    <a href="{{ route('admin.enrollments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Enroll Student</a>
</div>
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2" method="GET">
            <div class="col-md-4">
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $s)<option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)<option value="{{ $c->id }}" @selected(request('course_id')==$c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Student</th><th>Subject</th><th>Semester</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($enrollments as $e)
            <tr>
                <td>{{ $enrollments->firstItem() + $loop->index }}</td>
                <td>
                    <div class="fw-semibold">{{ $e->student->user->name }}</div>
                    <div class="text-muted small">{{ $e->student->enrollment_number }}</div>
                </td>
                <td>{{ $e->subject->name }} <span class="text-muted small">({{ $e->subject->code }})</span></td>
                <td>{{ $e->semester->name }}</td>
                <td><span class="badge {{ $e->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($e->status) }}</span></td>
                <td>
                    <form method="POST" action="{{ route('admin.enrollments.destroy', $e) }}" class="d-inline" onsubmit="return confirm('Remove enrollment?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No enrollments found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($enrollments->hasPages())<div class="card-footer">{{ $enrollments->links() }}</div>@endif
</div>
@endsection
