@extends('layouts.teacher')
@section('title','My Students')
@section('page-title','My Students')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Students</li>
@endsection

@section('content')

<div class="card border-0 mb-4 overflow-hidden" style="background:linear-gradient(135deg,#0d9488,#0284c7)">
    <div class="card-body text-white py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center"
                 style="width:48px;height:48px;background:rgba(255,255,255,.15);flex-shrink:0">
                <i class="bi bi-people-fill" style="font-size:1.4rem"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-0">Students in My Subjects</h6>
                <div class="opacity-75 small mt-1">
                    @if($currentSemester)
                        {{ $currentSemester->name }}
                        @if($currentSemester->academicYear) &mdash; {{ $currentSemester->academicYear->name }} @endif
                    @else
                        No active semester configured
                    @endif
                </div>
            </div>
            <div class="ms-auto">
                <span class="badge bg-white text-primary fw-semibold px-3 py-2" style="font-size:.88rem">
                    {{ $students->count() }} student(s)
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-people text-primary"></i>
        <span class="fw-semibold">Student List</span>
    </div>
    <div class="card-body p-0">
        @if($students->isEmpty())
        <div class="empty-state py-5">
            <div class="empty-icon"><i class="bi bi-people" style="font-size:3rem;color:var(--clr-text-muted)"></i></div>
            <h6 class="mt-3 text-muted">No Students Found</h6>
            <p class="text-muted small mb-0">
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
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Enrollment</th>
                        <th>Course & Semester</th>
                        <th>Department</th>
                        <th>Attendance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($students as $s)
                <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                 style="width:36px;height:36px;background:var(--clr-primary,#4f46e5);font-size:.85rem">
                                {{ strtoupper(substr($s->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-semibold" style="font-size:.88rem">{{ $s->user->name }}</div>
                                <div class="text-muted" style="font-size:.73rem">{{ $s->user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="font-monospace small">{{ $s->enrollment_number ?? '-' }}</td>
                    <td>
                        <span class="badge bg-primary bg-opacity-10 text-primary me-1" style="font-size:.72rem">
                            {{ $s->course->code ?? ($s->course->name ?? '-') }}
                        </span>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:.72rem">
                            Sem {{ $s->current_semester }}
                        </span>
                    </td>
                    <td class="text-muted small">{{ $s->department->name ?? '-' }}</td>
                    <td>
                        @if($s->teacher_attendance_percentage === null)
                            <span class="text-muted small">No records</span>
                        @else
                            @php
                                $attendanceClass = $s->teacher_attendance_percentage >= 85
                                    ? 'success'
                                    : ($s->teacher_attendance_percentage >= 75 ? 'warning' : 'danger');
                            @endphp
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-{{ $attendanceClass }} bg-opacity-10 text-{{ $attendanceClass }}">
                                    {{ rtrim(rtrim(number_format($s->teacher_attendance_percentage, 1), '0'), '.') }}%
                                </span>
                                <span class="text-muted small">
                                    {{ $s->teacher_attendance_attended }}/{{ $s->teacher_attendance_total }}
                                </span>
                            </div>
                        @endif
                    </td>
                    <td>
                        @if(Route::has('admin.students.show'))
                        <a href="{{ route('admin.students.show', $s) }}"
                           class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.75rem">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
