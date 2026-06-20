@extends('layouts.admin')
@section('title', 'Placed Students')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Placed Students</h4>
            <span class="text-muted small">All selected placements</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('cmc.placements.export') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i> Export Current View
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
                            <th class="ps-3">Student</th>
                            <th>Drive / Company</th>
                            <th>Package</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($placements as $p)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-medium">{{ $p->student->user->name ?? '—' }}</div>
                                <div class="text-muted small">{{ $p->student->enrollment_number ?? '' }}</div>
                            </td>
                            <td>{{ $p->drive->company->name ?? $p->drive->title ?? '—' }}</td>
                            <td class="small">
                                @if($p->offered_package)
                                <span class="badge bg-success-subtle text-success">₹{{ $p->offered_package }} LPA</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $p->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No placements recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if($placements->hasPages())
    <div class="mt-3">{{ $placements->links() }}</div>
    @endif
</div>
@endsection
