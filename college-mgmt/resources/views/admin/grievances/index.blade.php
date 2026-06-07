@extends('layouts.admin')
@section('title', 'Student Grievances')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Student Grievances</h4>
            <span class="text-muted small">All submitted grievances across programs</span>
        </div>
        <div class="d-flex gap-2">
            @if($urgentCount > 0)
            <span class="badge bg-danger px-3 py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ $urgentCount }} Urgent
            </span>
            @endif
            @if($openCount > 0)
            <span class="badge bg-warning text-dark px-3 py-2">{{ $openCount }} Open</span>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(['open','in_progress','resolved','closed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach(['academic','fee','hostel','examination','facility','other'] as $c)
                        <option value="{{ $c }}" @selected(request('category') === $c)>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All priorities</option>
                        @foreach(['urgent','high','normal','low'] as $p)
                        <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.grievances.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Submitted</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grievances as $g)
                        <tr class="{{ $g->priority === 'urgent' && in_array($g->status, ['open','in_progress']) ? 'table-danger' : '' }}">
                            <td class="ps-3 text-muted small">#{{ str_pad($g->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div class="fw-medium small">{{ $g->student?->user?->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.7rem">{{ $g->student?->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="small" style="max-width:200px">{{ Str::limit($g->subject, 50) }}</td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">
                                    {{ ucfirst($g->category) }}
                                </span>
                            </td>
                            <td>{!! $g->priority_badge !!}</td>
                            <td>{!! $g->status_badge !!}</td>
                            <td class="small text-muted">{{ $g->assignedTo?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.grievances.show', $g) }}"
                                   class="btn btn-sm btn-outline-primary py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-check-circle me-2 text-success"></i>No grievances found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($grievances->hasPages())
    <div class="mt-3">{{ $grievances->links() }}</div>
    @endif
</div>
@endsection
