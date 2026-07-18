<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Section And Group Adjustments</div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th scope="col">Adjustment</th><th scope="col">Groups</th><th scope="col">Strength</th><th scope="col">Approval</th><th scope="col">Decision</th></tr></thead>
        <tbody>
            @forelse($groupAdjustments as $adjustment)
                <tr>
                    <td>{{ str_replace('_', ' ', $adjustment->adjustment_type) }}<div class="small text-muted">{{ $adjustment->student?->user?->name ?? 'Group-level' }}</div></td>
                    <td><div class="fw-semibold">{{ $adjustment->courseGroup?->name }}</div><div class="small text-muted">Target: {{ $adjustment->targetCourseGroup?->name ?? '-' }}</div></td>
                    <td>{{ $adjustment->from_strength }} -> {{ $adjustment->to_strength }}<div class="small text-muted">Target {{ $adjustment->target_from_strength }} -> {{ $adjustment->target_to_strength }}</div></td>
                    <td>{{ str_replace('_', ' ', $adjustment->status) }}<div class="small text-muted">{{ $adjustment->requires_dean_approval ? 'Dean approval required' : 'PMC approval' }}</div></td>
                    <td>
                        <form method="POST" action="{{ route('academics.pmc.course-group-adjustments.decide', $adjustment) }}" class="d-flex flex-column gap-1">
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
                <tr><td colspan="5" class="text-muted">No group adjustment requests.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="card-footer py-2">{{ $groupAdjustments->links() }}</div>
</div>
