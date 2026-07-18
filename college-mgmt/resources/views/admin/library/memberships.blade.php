@extends('layouts.admin')
@section('title', 'Library Memberships')
@section('page-title', 'Library Memberships')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.index') }}">Library</a></li>
    <li class="breadcrumb-item active">Memberships</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small">Showing {{ $memberships->total() }} membership record(s).</div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.library.memberships.export') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMembershipModal"><i class="bi bi-person-plus me-1"></i>Add Membership</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">User</th>
                        <th scope="col">Type</th>
                        <th scope="col">Max Books</th>
                        <th scope="col">Max Days</th>
                        <th scope="col">Fine/Day</th>
                        <th scope="col">Expiry</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memberships as $m)
                    <tr>
                        <td>{{ $m->user->name ?? 'Member name missing' }}<br><small class="text-muted">{{ $m->user->email ?? 'Email not linked' }}</small></td>
                        <td><span class="badge bg-secondary">{{ ucfirst($m->member_type) }}</span></td>
                        <td>{{ $m->max_books_allowed }}</td>
                        <td>{{ $m->max_days_allowed }}</td>
                        <td>Rs. {{ number_format((float) $m->fine_per_day, 2) }}</td>
                        <td>{{ $m->expiry_date ? \Carbon\Carbon::parse($m->expiry_date)->format('d M Y') : 'No Expiry' }}</td>
                        <td>
                            @if($m->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">
                            No library memberships are configured yet. Add a membership before issuing books so limits, due days, and fines can be applied.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($memberships->hasPages())
    <div class="card-footer">{{ $memberships->links() }}</div>
    @endif
</div>

{{-- Add Membership Modal --}}
<div class="modal fade" id="addMembershipModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.library.memberships.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>Add / Update Membership</h5>
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">User *</label>
                        <select aria-label="User" name="user_id" class="form-select" required>
                            <option value="">Select user</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Member Type *</label>
                        <select aria-label="Member Type" name="member_type" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Max Books *</label>
                            <input aria-label="Max Books Allowed" type="number" name="max_books_allowed" class="form-control" value="2" min="1" max="20" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Max Days *</label>
                            <input aria-label="Max Days Allowed" type="number" name="max_days_allowed" class="form-control" value="14" min="1" max="365" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fine Per Day (Rs.) *</label>
                        <input aria-label="Fine Per Day" type="number" name="fine_per_day" class="form-control" value="1.00" min="0" step="0.5" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expiry Date</label>
                        <input aria-label="Expiry Date" type="date" name="expiry_date" class="form-control">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActiveCheck" checked>
                        <label class="form-check-label" for="isActiveCheck">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Membership</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
