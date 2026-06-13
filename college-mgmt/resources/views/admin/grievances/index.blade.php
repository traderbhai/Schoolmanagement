@extends('layouts.admin')
@section('title', 'Student Grievances')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0">Student Grievances</h4>
            <span class="text-muted small">Assign, track, escalate, and resolve student support issues.</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.72rem;letter-spacing:.04em">Grievance Priority</div>
                <h5 class="fw-bold mb-1">{{ $grievancePriority['title'] }}</h5>
                <p class="text-muted mb-0">{{ $grievancePriority['body'] }}</p>
            </div>
            <a href="{{ $grievancePriority['route'] }}" class="btn btn-sm {{ $grievancePriority['level'] === 'danger' ? 'btn-danger' : ($grievancePriority['level'] === 'warning' ? 'btn-warning' : 'btn-primary') }}">
                <i class="bi bi-arrow-right-circle me-1"></i>{{ $grievancePriority['action'] }}
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-warning">{{ $openCount }}</div><div class="text-muted small">Active</div></div></div></div>
        <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-danger">{{ $urgentCount }}</div><div class="text-muted small">Urgent Active</div></div></div></div>
        <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-danger">{{ $overdueCount }}</div><div class="text-muted small">Older Than 7 Days</div></div></div></div>
        <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-bold text-success">{{ $resolvedCount }}</div><div class="text-muted small">Resolved</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        @foreach(['open','under_review','escalated','resolved','closed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All categories</option>
                        @foreach(['academic','financial','facility','faculty','administrative','other'] as $c)
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
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
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
                            <th>Title</th>
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
                        <tr class="{{ $g->priority === 'urgent' && in_array($g->status, ['open','under_review','escalated']) ? 'table-danger' : '' }}">
                            <td class="ps-3 text-muted small">#{{ str_pad($g->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                <div class="fw-medium small">{{ $g->student?->user?->name ?? '-' }}</div>
                                <div class="text-muted" style="font-size:.7rem">{{ $g->student?->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="small" style="max-width:240px">{{ \Illuminate\Support\Str::limit($g->title, 60) }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem">{{ ucfirst($g->category) }}</span></td>
                            <td>{!! $g->priority_badge !!}</td>
                            <td>{!! $g->status_badge !!}</td>
                            <td class="small text-muted">{{ $g->assignedTo?->name ?? '-' }}</td>
                            <td class="small text-muted">{{ $g->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.grievances.show', $g) }}" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4"><i class="bi bi-check-circle me-2 text-success"></i>No grievances found.</td>
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
