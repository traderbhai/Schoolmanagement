@extends('layouts.admin')
@section('title', 'Enrollments')
@section('page-title', 'Subject Enrollments')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Enrollments</li>
@endsection

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Subject Enrollments</h5>
        <div class="text-muted" style="font-size:.82rem">Manage student subject enrollments</div>
    </div>
    <a href="{{ route('admin.enrollments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Enroll Student
    </a>
</div>

<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Semester</label>
                <select aria-label="Semester" name="semester_id" class="form-select form-select-sm">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $s)
                        <option value="{{ $s->id }}" @selected(request('semester_id')==$s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm mb-1">Course</label>
                <select aria-label="Course" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}" @selected(request('course_id')==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
                @if(request()->hasAny(['semester_id','course_id']))
                    <a href="{{ route('admin.enrollments.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Subject</th>
                    <th scope="col">Semester</th>
                    <th scope="col">Enrolled</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($enrollments as $e)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:var(--clr-primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.78rem;flex-shrink:0">{{ strtoupper(substr($e->student->user->name,0,1)) }}</div>
                        <div>
                            <div class="fw-semibold" style="font-size:.875rem">{{ $e->student->user->name }}</div>
                            <div class="text-muted" style="font-size:.76rem">{{ $e->student->enrollment_number }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $e->subject->name }} <span class="text-muted" style="font-size:.78rem">({{ $e->subject->code }})</span></td>
                <td>{{ $e->semester->name }}</td>
                <td>{{ optional($e->created_at)->format('d M Y') ?? '–' }}</td>
                <td><span class="badge {{ $e->status === 'active' ? 'badge-active' : 'bg-secondary' }}">{{ ucfirst($e->status) }}</span></td>
                <td>
                    @if($e->status !== 'active')
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Remove"
                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                        data-action="{{ route('admin.enrollments.destroy', $e) }}"
                        data-name="{{ $e->student->user->name }} — {{ $e->subject->name }}">
                        <i class="bi bi-trash"></i>
                    </button>
                    @else
                    <span class="text-muted" style="font-size:.8rem">Active</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-person-lines-fill"></i></div>
                        <div class="mt-2 fw-semibold">No enrollments found</div>
                        <a href="{{ route('admin.enrollments.create') }}" class="btn btn-primary btn-sm mt-3">Enroll Student</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($enrollments->hasPages())
    <div class="card-footer">{{ $enrollments->links() }}</div>
    @endif
</div>
@endsection
