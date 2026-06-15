<div class="row g-3">
    <div class="col-xl-4"><div class="card shadow-sm h-100"><div class="card-header py-2 fw-semibold">Quality Scores</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Run</th><th>Overall</th><th>Hard</th><th>Soft</th></tr></thead><tbody>@foreach($quality as $score)<tr><td>{{ $score->generation_run_id }}</td><td>{{ $score->overall_score }}%</td><td>{{ $score->hard_conflicts }}</td><td>{{ $score->soft_warnings }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $quality->links() }}</div></div></div>
    <div class="col-xl-4"><div class="card shadow-sm h-100"><div class="card-header py-2 fw-semibold">Constraint Report</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Constraint</th><th>Severity</th></tr></thead><tbody>@foreach($constraints as $constraint)<tr><td>{{ $constraint->title }}</td><td>{{ $constraint->severity }}</td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $constraints->links() }}</div></div></div>
    <div class="col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-header py-2">
                <div class="fw-semibold">Notification Report</div>
                <div class="small text-muted">Visible filter summary: type={{ $notificationFilters['notification_type'] ?? 'all' }} | recipient={{ $notificationFilters['recipient_type'] ?? 'all' }} | status={{ $notificationFilters['status'] ?? 'all' }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Notice</th>
                            <th>Audience</th>
                            <th>Impact</th>
                            <th>Quality</th>
                            <th>Delivery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifications as $notification)
                            @php($meta = $notification->metadata ?? [])
                            @php($impact = $meta['impact_preview'] ?? [])
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $notification->title }}</div>
                                    <div class="small text-muted">{{ $notification->notification_type }} | {{ $notification->status }}</div>
                                </td>
                                <td>
                                    {{ $notification->recipient_type }}
                                    <div class="small text-muted">{{ $meta['audience_count'] ?? '-' }} recipient(s)</div>
                                </td>
                                <td>
                                    <div class="small">Students {{ $impact['affected_students'] ?? '-' }} | Faculty {{ $impact['affected_faculty'] ?? '-' }}</div>
                                    <div class="small text-muted">Rooms {{ $impact['affected_rooms'] ?? '-' }} | Groups {{ $impact['affected_groups'] ?? '-' }}</div>
                                    <div class="small text-muted">Synced {{ $meta['operational_entries_synced'] ?? '-' }} | Run #{{ $meta['generation_run_id'] ?? '-' }}</div>
                                </td>
                                <td>
                                    {{ $meta['quality_score'] ?? '-' }}%
                                    <div class="small text-muted">H{{ $meta['hard_conflicts'] ?? '-' }} / S{{ $meta['soft_warnings'] ?? '-' }}</div>
                                </td>
                                <td style="min-width: 210px">
                                    <form method="POST" action="{{ route('academics.pmc.timetable-notifications.update-status', $notification) }}" class="d-flex flex-column gap-1">
                                        @csrf @method('PATCH')
                                        <select name="status" class="form-select form-select-sm">
                                            @foreach(['queued', 'sent', 'read', 'failed', 'cancelled'] as $status)
                                                <option value="{{ $status }}" @selected($notification->status === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                        <input name="status_note" class="form-control form-control-sm" placeholder="Status note">
                                        <button class="btn btn-sm btn-outline-primary">Update</button>
                                    </form>
                                    @if(in_array($notification->status, ['failed', 'cancelled'], true))
                                        <form method="POST" action="{{ route('academics.pmc.timetable-notifications.retry', $notification) }}" class="d-flex flex-column gap-1 mt-2">
                                            @csrf
                                            <input name="retry_note" class="form-control form-control-sm" placeholder="Retry note">
                                            <button class="btn btn-sm btn-outline-warning">Retry</button>
                                        </form>
                                    @endif
                                    <div class="small text-muted mt-1">
                                        @if(!empty($meta['retry_count']))
                                            Retry {{ $meta['retry_count'] }} | next {{ $meta['next_retry_at'] ?? '-' }}
                                            @if(!empty($meta['latest_status_note'])) | @endif
                                        @endif
                                        {{ $meta['latest_status_note'] ?? '' }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2">{{ $notifications->links() }}</div>
        </div>
    </div>
</div>
@isset($sessionDemands)
<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Weekly Session Demand Report</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Group</th><th>Type</th><th>Required</th><th>Scheduled</th><th>Unscheduled</th><th>Status</th></tr></thead><tbody>
        @forelse($sessionDemands as $demand)<tr><td><div class="fw-semibold">{{ $demand->courseGroup?->name }}</div><div class="small text-muted">{{ $demand->courseGroup?->subject?->name }}</div></td><td>{{ $demand->session_type }}</td><td>{{ $demand->required_sessions_per_week }}</td><td>{{ $demand->scheduled_sessions }}</td><td>{{ $demand->unscheduled_sessions }}</td><td>{{ $demand->status }}</td></tr>@empty<tr><td colspan="6" class="text-muted">No weekly session demand records.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $sessionDemands->links() }}</div>
</div>
@endisset
@isset($publishChecks)
<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Publish Blockers And Checks</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Check</th><th>Status</th><th>Severity</th><th>Required Role</th></tr></thead><tbody>
        @forelse($publishChecks as $check)<tr><td><div class="fw-semibold">{{ $check->title }}</div><div class="small text-muted">{{ $check->description }}</div></td><td>{{ $check->status }}</td><td>{{ $check->severity }}</td><td>{{ $check->required_role }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No publish checks.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $publishChecks->links() }}</div>
</div>
@endisset
@isset($roomReadinessReviews)
<div class="card shadow-sm mt-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Room And Lab Readiness Reviews</span>
        <form method="POST" action="{{ route('academics.pmc.room-readiness-reviews.refresh') }}" class="m-0">
            @csrf
            <button class="btn btn-sm btn-outline-primary">Refresh Room Readiness</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Room</th><th>Use</th><th>Capacity</th><th>Lab</th><th>Band</th><th>Status</th><th>Decision</th></tr></thead><tbody>
        @forelse($roomReadinessReviews as $review)
            <tr>
                <td><div class="fw-semibold">{{ $review->classroom?->name ?? $review->classroom?->room_number ?? 'Room' }}</div><div class="small text-muted">{{ $review->generationRun?->title ?? 'Latest run' }}</div></td>
                <td>{{ $review->scheduled_classes }} class(es)</td>
                <td>{{ $review->max_group_strength }} / {{ $review->room_capacity }}</td>
                <td>{{ $review->lab_required ? ($review->lab_ready ? 'ready' : 'not ready') : 'not required' }}</td>
                <td><span class="badge text-bg-{{ $review->readiness_band === 'blocked' ? 'danger' : ($review->readiness_band === 'warning' ? 'warning' : 'success') }}">{{ $review->readiness_band }}</span><div class="small text-muted">{{ collect($review->risk_reasons ?: [])->join(', ') ?: 'clear' }}</div></td>
                <td>{{ str_replace('_', ' ', $review->status) }}<div class="small text-muted">{{ $review->reviewer?->name ?? 'Pending PMC review' }}</div></td>
                <td>
                    <form method="POST" action="{{ route('academics.pmc.room-readiness-reviews.decide', $review) }}" class="d-flex flex-column gap-1">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm">
                            <option value="approved">approved</option>
                            <option value="approved_with_exception">approved with exception</option>
                            <option value="revision_required">revision required</option>
                            <option value="rejected">rejected</option>
                        </select>
                        <input name="decision_note" class="form-control form-control-sm" placeholder="Decision note">
                        <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted">No room readiness reviews yet. Refresh after timetable generation.</td></tr>
        @endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $roomReadinessReviews->links() }}</div>
</div>
@endisset
@isset($resolutionActions)
<div class="card shadow-sm mt-3">
    <div class="card-header py-2 fw-semibold">Conflict Resolution Actions</div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Action</th><th>Constraint</th><th>Owner</th><th>Status</th><th>Closed</th></tr></thead><tbody>
        @forelse($resolutionActions as $action)<tr><td>{{ $action->title }}</td><td>{{ $action->constraint?->title }}</td><td>{{ $action->owner?->name ?? 'PMC' }}</td><td>{{ $action->status }}</td><td>{{ optional($action->closed_at)->format('d M Y') ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No conflict resolution actions.</td></tr>@endforelse
    </tbody></table></div><div class="card-footer py-2">{{ $resolutionActions->links() }}</div>
</div>
@endisset
