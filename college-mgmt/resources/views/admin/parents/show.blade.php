@extends('layouts.admin')
@section('title', $parent->user->name)
@section('page-title', 'Parent Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.parents.index') }}">Parents</a></li>
    <li class="breadcrumb-item active">Profile</li>
@endsection

@section('content')
<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="user-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:1.5rem;background:var(--clr-primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;">
                    {{ strtoupper(substr($parent->user->name, 0, 2)) }}
                </div>
                <h5 class="fw-bold mb-1">{{ $parent->user->name }}</h5>
                <div class="text-muted mb-1">{{ $parent->user->email }}</div>
                <span class="badge bg-secondary">{{ ucfirst($parent->relation) }}</span>
            </div>
            <div class="card-footer">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Phone</td><td>{{ $parent->phone ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Occupation</td><td>{{ $parent->occupation ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Annual Income</td><td>{{ $parent->annual_income ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Address</td><td>{{ $parent->address ?? '—' }}</td></tr>
                </table>
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.parents.edit', $parent) }}" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-pencil me-1"></i>Edit</a>
                <a href="{{ route('admin.parents.index') }}" class="btn btn-sm btn-outline-secondary flex-fill"><i class="bi bi-arrow-left me-1"></i>Back</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
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
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($parent->students as $student)
                    <tr>
                        <td class="fw-semibold">{{ $student->user->name }}</td>
                        <td>{{ $student->enrollment_number }}</td>
                        <td>{{ optional($student->course)->name ?? '—' }}</td>
                        <td>{{ optional($student->department)->name ?? '—' }}</td>
                        <td><span class="badge {{ $student->status === 'active' ? 'badge-active' : 'bg-secondary' }}">{{ ucfirst($student->status) }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No students linked yet.</td>
                    </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
