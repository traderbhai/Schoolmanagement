@extends('layouts.admin')
@section('title', 'Accounts — Reports')

@section('content')
<h4 class="mb-4"><i class="bi bi-file-earmark-bar-graph me-2 text-primary"></i>Fee Collection Reports</h4>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Program-wise Summary</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Program</th><th>Billed</th><th>Collected</th><th>Outstanding</th><th>Collection %</th></tr>
                </thead>
                <tbody>
                @forelse($programs as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td>₹{{ number_format($p->total_billed, 0) }}</td>
                        <td class="text-success">₹{{ number_format($p->total_collected, 0) }}</td>
                        <td class="text-danger">₹{{ number_format($p->outstanding, 0) }}</td>
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
                    <tr><td colspan="5" class="text-center text-muted">No data.</td></tr>
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
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Batch</th><th>Program</th><th>Students</th><th>Collected</th></tr>
                </thead>
                <tbody>
                @forelse($batches as $b)
                    <tr>
                        <td>{{ $b->name }}</td>
                        <td>{{ $b->program?->name ?? '—' }}</td>
                        <td>{{ $b->student_count }}</td>
                        <td>₹{{ number_format($b->total_collected, 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
