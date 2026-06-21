@extends('layouts.admin')
@section('title', 'Placement Drives')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Placement Drives</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('cmc.drives.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Current View
            </a>
            <a href="{{ route('cmc.drives.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Drive
            </a>
            <a href="{{ route('cmc.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active (upcoming or ongoing)</option>
                        @foreach(['upcoming','ongoing','completed','cancelled'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Company</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">All companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button class="btn btn-sm btn-primary">Apply Filters</button>
                    <a href="{{ route('cmc.drives') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <span class="small text-muted align-self-center">Showing {{ $drives->total() }} drive(s)</span>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Company / Drive</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Date</th>
                            <th class="text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drives as $drive)
                        @php
                            $sc = ['upcoming'=>'success','ongoing'=>'primary','completed'=>'info','cancelled'=>'secondary'];
                            $s = $drive->status ?? 'upcoming';
                        @endphp
                        <tr>
                            <td class="ps-3 fw-medium">{{ $drive->company->name ?? $drive->title ?? '—' }}</td>
                            <td class="small text-muted">{{ ucfirst($drive->type ?? 'placement') }}</td>
                            <td><span class="badge bg-{{ $sc[$s] ?? 'secondary' }}-subtle text-{{ $sc[$s] ?? 'secondary' }}">{{ ucfirst($s) }}</span></td>
                            <td>{{ $drive->placements_count }}</td>
                            <td class="small text-muted">{{ $drive->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('cmc.drives.applications', $drive) }}" class="btn btn-sm btn-outline-info py-0 px-2" title="Applications">
                                    <i class="bi bi-people"></i>
                                </a>
                                <a href="{{ route('cmc.drives.edit', $drive) }}" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-action="{{ route('cmc.drives.destroy', $drive) }}"
                                    data-name="{{ $drive->title }}" title="Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No drives found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if($drives->hasPages())
    <div class="mt-3">{{ $drives->links() }}</div>
    @endif
</div>
@endsection
