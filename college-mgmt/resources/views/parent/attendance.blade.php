@extends('layouts.parent')
@section('title', 'Attendance — '.$student->user->name)
@section('page-title', 'Attendance')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parent.children') }}">My Children</a></li>
    <li class="breadcrumb-item active">Attendance</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="fw-bold mb-0">{{ $student->user->name }} — Attendance</h5>
        <div class="text-muted" style="font-size:.82rem">{{ optional($student->course)->name }}</div>
    </div>
    <a href="{{ route('parent.children') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="GET" class="mb-3">
    <div class="d-flex gap-2 align-items-center">
        <select name="semester_id" class="form-select form-select-sm" style="max-width:260px" onchange="this.form.submit()">
            @foreach($semesters as $sem)
            <option value="{{ $sem->id }}" @selected($sem->id == $semesterId)>
                {{ $sem->name }} — {{ $sem->academicYear->name ?? '' }}
            </option>
            @endforeach
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Subject</th>
                    <th>Total</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Late</th>
                    <th>%</th>
                    <th>Status</th>
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
                <td colspan="7" class="text-center text-muted py-3">No attendance records for this semester.</td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
