@extends('layouts.admin')
@section('title', 'Placement Drives')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Placement Drives</h4>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.placement-drives.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Drive
            </a>
            <a href="{{ route('cmc.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

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
                            $sc = ['open'=>'success','active'=>'primary','closed'=>'secondary','completed'=>'info'];
                            $s = $drive->status ?? 'open';
                        @endphp
                        <tr>
                            <td class="ps-3 fw-medium">{{ $drive->company->name ?? $drive->title ?? '—' }}</td>
                            <td class="small text-muted">{{ ucfirst($drive->type ?? 'placement') }}</td>
                            <td><span class="badge bg-{{ $sc[$s] ?? 'secondary' }}-subtle text-{{ $sc[$s] ?? 'secondary' }}">{{ $s }}</span></td>
                            <td>{{ $drive->placements->count() }}</td>
                            <td class="small text-muted">{{ $drive->created_at->format('d M Y') }}</td>
                            <td class="text-end pe-3">
                                <a href="{{ route('admin.placement-drives.show', $drive) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
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
