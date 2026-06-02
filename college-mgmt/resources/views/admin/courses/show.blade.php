@extends('layouts.admin')
@section('title','Course Details')
@section('page-title','Course Details')
@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold">{{ $course->name }}</h5>
                <span class="badge bg-secondary mb-2">{{ $course->code }}</span>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted">Department</td><td class="fw-semibold">{{ $course->department->name }}</td></tr>
                    <tr><td class="text-muted">Duration</td><td>{{ $course->duration_years }} Years</td></tr>
                    <tr><td class="text-muted">Semesters</td><td>{{ $course->total_semesters }}</td></tr>
                    <tr><td class="text-muted">Students</td><td>{{ $course->students->count() }}</td></tr>
                    <tr><td class="text-muted">Status</td><td><span class="badge {{ $course->is_active ? 'bg-success' : 'bg-danger' }}">{{ $course->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                </table>
                @if($course->description)<p class="text-muted small mt-2">{{ $course->description }}</p>@endif
                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-sm btn-outline-primary mt-2">Edit</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Enrolled Students</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Enrollment No.</th><th>Semester</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($course->students as $s)
                    <tr>
                        <td>{{ $s->user->name }}</td>
                        <td><code>{{ $s->enrollment_number }}</code></td>
                        <td>Sem {{ $s->current_semester }}</td>
                        <td><span class="badge {{ $s->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($s->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No students enrolled.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
