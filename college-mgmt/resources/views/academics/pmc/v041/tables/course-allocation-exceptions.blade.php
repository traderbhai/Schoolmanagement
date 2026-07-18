<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Course Basket Exceptions</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Student</th><th scope="col">Subject</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Flags</th><th scope="col">Decision</th></tr></thead>
        <tbody>
            @forelse($allocationExceptions as $exception)
                <tr>
                    <td>{{ $exception->student?->user?->name ?? $exception->student?->enrollment_number ?? $exception->student?->roll_number ?? $exception->student?->student_id ?? 'Unassigned student' }}</td>
                    <td><div class="fw-semibold">{{ $exception->subject?->name ?? $exception->subject?->code ?? 'Unassigned subject' }}</div><div class="small text-muted">{{ $exception->term?->name ?? 'Unassigned term' }}</div></td>
                    <td>{{ str_replace('_', ' ', $exception->exception_type) }}<div class="small text-muted">{{ $exception->requires_dean_approval ? 'Dean approval required' : 'PMC approval' }}</div></td>
                    <td>{{ str_replace('_', ' ', $exception->status) }}<div class="small text-muted">{{ $exception->decider?->name ?? $exception->requester?->name ?? 'Pending' }}</div></td>
                    <td>{{ collect($exception->validation_flags ?: [])->join(', ') ?: 'clear' }}</td>
                    <td>
                        <form method="POST" action="{{ route('academics.pmc.course-allocation-exceptions.decide', $exception) }}" class="d-flex flex-column gap-1">
                            @csrf @method('PATCH')
                            <select aria-label="Status" name="status" class="form-select form-select-sm">
                                <option value="approved">approved</option>
                                <option value="returned">returned</option>
                                <option value="rejected">rejected</option>
                            </select>
                            <input aria-label="Decision note" name="decision_note" class="form-control form-control-sm" placeholder="Decision note" required>
                            <button class="btn btn-sm btn-outline-primary">Save decision</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No course basket exception requests.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="card-footer py-2">{{ $allocationExceptions->links() }}</div>
</div>
