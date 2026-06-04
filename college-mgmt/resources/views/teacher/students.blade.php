@extends('layouts.teacher')
@section('title','My Students')
@section('page-title','My Students')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Students</li>
@endsection

@section('content')
{{-- Semester Context --}}
<div class="alert alert-info d-flex align-items-center py-2 mb-3" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    @if($currentSemester)
        Students enrolled in your subjects &mdash; <strong>Semester: {{ $currentSemester->name }}</strong>
    @else
        Students enrolled in your subjects (no active semester)
    @endif
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-2 text-primary"></i>Student List</span>
        <span class="badge bg-primary rounded-pill">{{ $students->count() }} student(s)</span>
    </div>
    <div class="card-body p-0">
        @if($students->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-people display-4 d-block mb-3"></i>
                <h6>No students found.</h6>
                <p class="small mb-0">
                    @if($currentSemester)
                        No students are enrolled in your subjects for this semester.
                    @else
                        There is no active semester configured.
                    @endif
                </p>
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Roll No.</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Department</th>
                        <th>Enrollment No.</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($students as $s)
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td><code>{{ $s->roll_number }}</code></td>
                    <td>
                        <div class="fw-semibold">{{ $s->user->name }}</div>
                        <div class="text-muted small">{{ $s->user->email }}</div>
                    </td>
                    <td>{{ $s->course->name ?? '—' }}</td>
                    <td>{{ $s->department->name ?? '—' }}</td>
                    <td><span class="text-muted small">{{ $s->enrollment_number ?? '—' }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
