@extends('layouts.admin')
@section('title', 'Role Assignments')
@section('page-title', 'Access Control — Role Assignments')

@section('content')
@include('admin.partials.setup-sequence')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="text-muted mb-0">Manage departmental role assignments scoped to programs.</p>
    </div>
    <a href="{{ route('admin.role-assignments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Assignment
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
$roleLabels = [
    'dean_academics'   => ['label' => 'Dean of Academics',   'color' => 'primary'],
    'program_chair'    => ['label' => 'Program Chair',        'color' => 'success'],
    'exam_cell'        => ['label' => 'Exam Cell',            'color' => 'warning'],
    'hod'              => ['label' => 'Head of Department',   'color' => 'info'],
    'accounts_officer' => ['label' => 'Accounts Officer',     'color' => 'secondary'],
];
@endphp

@foreach($roles as $role)
@php
    $info  = $roleLabels[$role] ?? ['label' => $role, 'color' => 'dark'];
    $items = $assignments->get($role, collect());
@endphp
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="badge bg-{{ $info['color'] }}">{{ $info['label'] }}</span>
        <span class="text-muted small">({{ $items->count() }} assigned)</span>
    </div>
    <div class="card-body p-0">
        @if($items->isEmpty())
            <p class="text-muted p-3 mb-0">No assignments yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Email</th>
                        <th scope="col">Program Scope</th>
                        <th scope="col">Batch Scope</th>
                        <th scope="col">Assigned By</th>
                        <th scope="col">Assigned At</th>
                        <th scope="col">Status</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $a)
                <tr>
                    <td>{{ $a->user?->name ?? '—' }}</td>
                    <td>{{ $a->user?->email ?? '—' }}</td>
                    <td>{{ $a->program?->name ?? '<span class="text-muted">All Programs</span>' }}</td>
                    <td>{{ $a->batch?->id ?? '<span class="text-muted">—</span>' }}</td>
                    <td>{{ $a->assignedBy?->name ?? '—' }}</td>
                    <td>{{ $a->assigned_at?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @if($a->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.role-assignments.destroy', $a) }}" onsubmit="return confirm('Revoke scoped role assignment for {{ addslashes($a->user?->name ?? 'this user') }}? Confirm program/batch visibility, approvals, reports, and portal access should be removed for this scope.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Revoke</button>
                        </form>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endforeach
@endsection
