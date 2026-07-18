@extends('layouts.admin')
@section('title', 'Student Promotions')
@section('page-title', 'Term Promotion Records')

@section('content')
<div class="container-fluid py-3">

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-arrow-up-circle me-2 text-success"></i>Promotion Records</span>
            <span class="badge bg-secondary">{{ $promotions->total() }} records</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Student</th>
                        <th scope="col">Batch</th>
                        <th scope="col">From Term</th>
                        <th scope="col">To Term</th>
                        <th scope="col">Status</th>
                        <th scope="col">Processed By</th>
                        <th scope="col">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $i => $promo)
                        @php
                            $statusColor = match($promo->status ?? '') {
                                'promoted'  => 'success',
                                'detained'  => 'danger',
                                'withheld'  => 'warning',
                                default     => 'secondary',
                            };
                            $textDark = $promo->status === 'withheld' ? ' text-dark' : '';
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $promotions->firstItem() + $i }}</td>
                            <td>
                                <div class="fw-semibold">{{ $promo->student->user->name ?? '—' }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $promo->student->enrollment_number ?? '' }}</div>
                            </td>
                            <td class="text-muted">{{ $promo->student->batch->name ?? '—' }}</td>
                            <td>{{ $promo->currentTerm->name ?? '—' }}</td>
                            <td>{{ $promo->promotedToTerm->name ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $statusColor }}{{ $textDark }}">
                                    {{ ucfirst($promo->status ?? 'unknown') }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $promo->processedBy->name ?? '—' }}</td>
                            <td class="text-muted" style="max-width:200px;white-space:normal">{{ $promo->remarks ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                No promotion records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($promotions->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
            <div class="text-muted small">
                Showing {{ $promotions->firstItem() }}–{{ $promotions->lastItem() }} of {{ $promotions->total() }}
            </div>
            {{ $promotions->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

</div>
@endsection
