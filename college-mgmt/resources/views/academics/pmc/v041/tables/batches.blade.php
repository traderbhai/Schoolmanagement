<div class="card shadow-sm mb-3">
    <div class="card-header py-2 fw-semibold">Allocation Batches</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Batch</th><th scope="col">Program</th><th scope="col">Status</th><th scope="col">Students</th><th scope="col">Core</th><th scope="col">Conflicts</th></tr></thead>
        <tbody>@forelse($batches as $batch)<tr>
            <td><div class="fw-semibold">{{ $batch->title }}</div><div class="small text-muted">{{ $batch->created_at->format('d M Y') }}</div></td>
            <td>{{ $batch->program?->code ?? 'All' }}</td><td>{{ $batch->status }}</td><td>{{ $batch->student_count }}</td><td>{{ $batch->core_allocations }}</td><td>{{ $batch->conflict_count }}</td>
        </tr>@empty<tr><td colspan="6" class="text-muted">No allocation batches.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $batches->links() }}</div>
</div>
<div class="card shadow-sm">
    <div class="card-header py-2 fw-semibold">Student Allocations</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Student</th><th scope="col">Subject</th><th scope="col">Type</th><th scope="col">Approval</th><th scope="col">Basket</th></tr></thead>
        <tbody>@forelse($allocations as $allocation)<tr>
            <td>{{ $allocation->student?->user?->name ?? $allocation->student?->enrollment_number ?? $allocation->student?->roll_number ?? $allocation->student?->student_id ?? 'Unassigned student' }}</td><td>{{ $allocation->subject?->name ?? $allocation->subject?->code ?? 'Unassigned subject' }}</td><td>{{ $allocation->allocation_type }}</td><td>{{ $allocation->approval_status }}</td><td>{{ $allocation->basket_status }}</td>
        </tr>@empty<tr><td colspan="5" class="text-muted">No student course allocations.</td></tr>@endforelse</tbody>
    </table></div><div class="card-footer py-2">{{ $allocations->links() }}</div>
</div>
