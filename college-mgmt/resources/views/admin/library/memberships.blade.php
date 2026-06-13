@extends('layouts.admin')
@section('title', 'Library Memberships')
@section('page-title', 'Library Memberships')
@section('content')
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="bi bi-plus-lg me-1"></i>Add Membership</button>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>User</th><th>Type</th><th>Max Books</th><th>Max Days</th><th>Fine/Day</th><th>Expiry</th><th>Active</th></tr></thead>
            <tbody>
                @forelse($memberships as $m)
                <tr>
                    <td>{{ $m->user?->name ?? '—' }}</td>
                    <td><span class="badge bg-info text-capitalize">{{ $m->member_type }}</span></td>
                    <td>{{ $m->max_books_allowed }}</td>
                    <td>{{ $m->max_days_allowed }} days</td>
                    <td>₹{{ $m->fine_per_day }}/day</td>
                    <td><small>{{ $m->expiry_date ?? 'No expiry' }}</small></td>
                    <td><span class="badge bg-{{ $m->is_active ? 'success' : 'secondary' }}">{{ $m->is_active ? 'Active' : 'Inactive' }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No memberships yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($memberships->hasPages())<div class="card-footer bg-white">{{ $memberships->links() }}</div>@endif
</div>
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('admin.library.memberships.store') }}">@csrf
            <div class="modal-header"><h6 class="modal-title">Add/Update Membership</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label small">User</label>
                    <select name="user_id" class="form-select form-select-sm" required>
                        <option value="">— Select —</option>
                        @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small">Type</label><select name="member_type" class="form-select form-select-sm"><option value="student">Student</option><option value="teacher">Teacher</option><option value="staff">Staff</option></select></div>
                    <div class="col-6"><label class="form-label small">Max Books</label><input type="number" name="max_books_allowed" class="form-control form-control-sm" value="2" min="1" max="10"></div>
                    <div class="col-6"><label class="form-label small">Max Days</label><input type="number" name="max_days_allowed" class="form-control form-control-sm" value="14" min="1"></div>
                    <div class="col-6"><label class="form-label small">Fine/Day (₹)</label><input type="number" name="fine_per_day" class="form-control form-control-sm" value="1.00" step="0.50" min="0"></div>
                    <div class="col-12"><label class="form-label small">Expiry Date</label><input type="date" name="expiry_date" class="form-control form-control-sm"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary">Save</button></div>
        </form>
    </div></div>
</div>
@endsection
