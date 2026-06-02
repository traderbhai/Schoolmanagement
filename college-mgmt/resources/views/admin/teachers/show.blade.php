@extends('layouts.admin')
@section('title','Teacher Profile')
@section('page-title','Teacher Profile')
@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;font-size:2rem">
                    {{ strtoupper(substr($teacher->user->name, 0, 1)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $teacher->user->name }}</h5>
                <div class="text-muted small">{{ $teacher->designation }}</div>
                <div class="text-muted small">{{ $teacher->user->email }}</div>
                <span class="badge {{ $teacher->status === 'active' ? 'bg-success' : 'bg-warning text-dark' }} mt-2">{{ ucfirst($teacher->status) }}</span>
            </div>
            <div class="card-body border-top">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted">Employee ID</td><td class="fw-semibold">{{ $teacher->employee_id }}</td></tr>
                    <tr><td class="text-muted">Department</td><td>{{ $teacher->department->name }}</td></tr>
                    <tr><td class="text-muted">Qualification</td><td>{{ $teacher->qualification ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Specialization</td><td>{{ $teacher->specialization ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Phone</td><td>{{ $teacher->phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Joined</td><td>{{ optional($teacher->date_of_joining)->format('d M Y') ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Type</td><td>{{ ucfirst(str_replace('_',' ',$teacher->employment_type)) }}</td></tr>
                    @if($currentSemester)
                    <tr><td class="text-muted">Weekly Load</td><td><span class="badge bg-primary">{{ $weeklyLoad }} periods</span></td></tr>
                    @endif
                </table>
                <a href="{{ route('admin.teachers.edit', $teacher) }}" class="btn btn-sm btn-outline-primary mt-3 w-100">Edit Profile</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Current Timetable</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Day</th><th>Time Slot</th><th>Subject</th><th>Course</th></tr></thead>
                    <tbody>
                    @forelse($teacher->timetableEntries->where('is_active', true) as $e)
                    <tr>
                        <td>{{ $e->day_name }}</td>
                        <td>{{ $e->slot->start_time }} – {{ $e->slot->end_time }}</td>
                        <td>{{ $e->subject->name }}</td>
                        <td>{{ $e->course->code }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No timetable entries.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
