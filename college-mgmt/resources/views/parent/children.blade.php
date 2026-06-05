@extends('layouts.parent')
@section('title', 'My Children')
@section('page-title', 'My Children')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('parent.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Children</li>
@endsection

@section('content')
<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Linked Students</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Enrollment</th>
                    <th>Course</th>
                    <th>Department</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($children as $student)
            <tr>
                <td class="fw-semibold">{{ $student->user->name }}</td>
                <td>{{ $student->enrollment_number }}</td>
                <td>{{ optional($student->course)->name ?? '—' }}</td>
                <td>{{ optional($student->department)->name ?? '—' }}</td>
                <td>{{ $student->current_semester ?? '—' }}</td>
                <td><span class="badge {{ $student->status === 'active' ? 'badge-active' : 'bg-secondary' }}">{{ ucfirst($student->status) }}</span></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('parent.children.attendance', $student) }}" class="btn btn-sm btn-outline-secondary" title="Attendance"><i class="bi bi-check2-square"></i></a>
                        <a href="{{ route('parent.children.results', $student) }}" class="btn btn-sm btn-outline-secondary" title="Results"><i class="bi bi-award"></i></a>
                        <a href="{{ route('parent.children.fees', $student) }}" class="btn btn-sm btn-outline-secondary" title="Fees"><i class="bi bi-cash-coin"></i></a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <div class="empty-state py-4 text-center">
                        <div class="fw-semibold">No children linked yet</div>
                        <div class="text-muted">Contact the administration.</div>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
