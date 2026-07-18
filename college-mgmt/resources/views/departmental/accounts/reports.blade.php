@extends('layouts.admin')
@section('title', 'Accounts - Reports')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Fee Collection Reports</h4>
        <p class="text-muted mb-0">Demand-based billed, collected, and outstanding totals for active fee follow-up.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('accounts.export-fee-collections') }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-download me-1"></i> Collections CSV
        </a>
        <a href="{{ route('accounts.export-outstanding') }}" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-download me-1"></i> Outstanding CSV
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Program-wise Summary</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Program</th>
                        <th scope="col">Billed</th>
                        <th scope="col">Collected</th>
                        <th scope="col">Outstanding</th>
                        <th scope="col" style="min-width:160px">Collection %</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($programs as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td>Rs. {{ number_format($p->total_billed, 0) }}</td>
                        <td class="text-success">Rs. {{ number_format($p->total_collected, 0) }}</td>
                        <td class="text-danger">Rs. {{ number_format($p->outstanding, 0) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:10px">
                                    <div class="progress-bar bg-{{ $p->collection_pct >= 70 ? 'success' : 'warning' }}"
                                         style="width:{{ $p->collection_pct }}%"></div>
                                </div>
                                <span class="small">{{ $p->collection_pct }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No active programs are available for fee reporting yet. Create programs and fee demands before using the program-wise finance report.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Batch-wise Summary</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Batch</th>
                        <th scope="col">Program</th>
                        <th scope="col">Active Students</th>
                        <th scope="col">Billed</th>
                        <th scope="col">Collected</th>
                        <th scope="col">Outstanding</th>
                        <th scope="col" style="min-width:160px">Collection %</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($batches as $b)
                    <tr>
                        <td>{{ $b->name }}</td>
                        <td>{{ $b->program?->name ?? '-' }}</td>
                        <td>{{ $b->student_count }}</td>
                        <td>Rs. {{ number_format($b->total_billed, 0) }}</td>
                        <td class="text-success">Rs. {{ number_format($b->total_collected, 0) }}</td>
                        <td class="text-danger">Rs. {{ number_format($b->outstanding, 0) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:10px">
                                    <div class="progress-bar bg-{{ $b->collection_pct >= 70 ? 'success' : 'warning' }}"
                                         style="width:{{ $b->collection_pct }}%"></div>
                                </div>
                                <span class="small">{{ $b->collection_pct }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No batches are available for fee reporting yet. Add batches, active students, and fee demands before using the batch-wise finance report.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
