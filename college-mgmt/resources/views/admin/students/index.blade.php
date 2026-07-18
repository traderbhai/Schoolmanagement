@extends('layouts.admin')
@section('title', 'Students')
@section('page-title', 'Students')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Students</li>
@endsection

@section('content')

{{-- Page Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-bold">Students</h5>
        <div class="text-muted" style="font-size:.82rem">Manage student records and enrollments</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.students.export') }}" class="btn btn-sm btn-outline-success"><i class="bi bi-download me-1"></i>Export CSV</a>
        <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Add Student
        </a>
    </div>
</div>

{{-- Filter Bar --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label form-label-sm mb-1">Search</label>
                <input aria-label="Student search" type="text" name="search" class="form-control form-control-sm" placeholder="Name, email, enrollment…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Department</label>
                <select aria-label="Department" name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected(request('department_id')==$d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Course</label>
                <select aria-label="Course" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}" @selected(request('course_id')==$c->id)>{{ $c->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-1">Status</label>
                <select aria-label="Status" name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['active','inactive','graduated','dropped'] as $s)
                        <option value="{{ $s }}" @selected(request('status')==$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary btn-sm">Search</button>
                @if(request()->hasAny(['search','department_id','course_id','status']))
                    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Data Table --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Enrollment No.</th>
                    <th scope="col">Course</th>
                    <th scope="col">Sem</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($students as $s)
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--clr-primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:.82rem;flex-shrink:0">{{ strtoupper(substr($s->user->name,0,1)) }}</div>
                        <div>
                            <a href="{{ route('admin.students.show', $s) }}" class="text-decoration-none fw-semibold" style="color:var(--clr-text)">{{ $s->user->name }}</a>
                            <div class="text-muted" style="font-size:.78rem">{{ $s->user->email }}</div>
                        </div>
                    </div>
                </td>
                <td><code>{{ $s->enrollment_number }}</code></td>
                <td><span class="badge bg-light text-dark border">{{ $s->course->code }}</span></td>
                <td>Sem {{ $s->current_semester }}</td>
                <td>
                    <span class="badge {{ match($s->status){ 'active'=>'badge-active','inactive'=>'bg-secondary','graduated'=>'badge-paid','dropped'=>'badge-danger',default=>'bg-secondary' } }}">
                        {{ ucfirst($s->status) }}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                            data-action="{{ route('admin.students.destroy', $s) }}"
                            data-name="{{ $s->user->name }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state py-5">
                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                        <div class="mt-2 fw-semibold">No students found</div>
                        <div class="text-muted" style="font-size:.85rem">Try adjusting your filters or add a new student.</div>
                        <a href="{{ route('admin.students.create') }}" class="btn btn-primary btn-sm mt-3"><i class="bi bi-person-plus me-1"></i>Add Student</a>
                    </div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($students->hasPages())
    <div class="card-footer">{{ $students->links() }}</div>
    @endif
</div>
@endsection
