@extends('layouts.parent')
@section('title', 'Attendance - '.$student->user->name)
@section('page-title', 'Attendance')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parent.children') }}">My Children</a></li>
    <li class="breadcrumb-item active">Attendance</li>
@endsection

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h5 class="fw-bold mb-0">{{ $student->user->name }} - Attendance</h5>
        <div class="text-muted" style="font-size:.82rem">{{ optional($student->program)->name ?? optional($student->course)->name }}</div>
    </div>
    <a href="{{ route('parent.children') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="GET" class="mb-3">
    <div class="d-flex gap-2 align-items-center">
        <select aria-label="Semester" name="semester_id" class="form-select form-select-sm" style="max-width:260px" onchange="this.form.submit()">
            @foreach($semesters as $sem)
            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                {{ $sem->name }} - {{ $sem->academicYear->name ?? '' }}
            </option>
            @endforeach
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Total</th>
                        <th scope="col">Present</th>
                        <th scope="col">Absent</th>
                        <th scope="col">Late</th>
                        <th scope="col">%</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($report as $row)
                <tr>
                    <td class="fw-semibold">{{ $row['subject'] }}</td>
                    <td>{{ $row['total'] }}</td>
                    <td>{{ $row['present'] }}</td>
                    <td>{{ $row['absent'] }}</td>
                    <td>{{ $row['late'] }}</td>
                    <td>{{ $row['pct'] }}%</td>
                    <td>
                        @if($row['low'])
                        <span class="badge bg-danger">Low</span>
                        @else
                        <span class="badge badge-active">Good</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark mb-1">No published attendance records are available for this semester yet</div>
                        <div class="small">Attendance appears here after the timetable is published and faculty mark classes for your child's enrolled subjects. Draft timetable rows and out-of-scope subjects are not shown to parents.</div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
