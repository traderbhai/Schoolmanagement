@extends('layouts.admin')

@section('title', 'Counsellor Performance')

@section('content')
<div class="v037">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3 class="fw-bold mb-1">Counsellor Performance</h3>
        <div class="text-muted small">Review targets, script compliance, conversion output, and coaching actions before the daily manager huddle.</div>
    </div>
    <a href="{{ route('admission.counsellor-desk.index') }}" class="btn btn-outline-primary btn-sm">Counsellor Desk</a>
</div>

<div class="alert alert-info py-2 small mb-3">
    <strong>Manager workflow:</strong> check scorecards first, add coaching notes for weak bands, then open the Counsellor Desk to review the same user's leads, reminders, and applicant blockers.
</div>

<div class="row g-2 mb-3">
@foreach($dashboard['stats'] as $label => $value)
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
@endforeach
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-bold">Target Scorecards</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Counsellor</th><th scope="col">Calls</th><th scope="col">Follow-ups</th><th scope="col">Applications</th><th scope="col">Enrollments</th><th scope="col">Script</th><th scope="col">Overall</th><th scope="col">Coaching</th></tr></thead>
            <tbody>
            @forelse($dashboard['rows'] as $row)
                <tr>
                    <td><strong>{{ $row['user']?->name }}</strong><div class="small text-muted">{{ $row['target']->period_start->format('d M') }} - {{ $row['target']->period_end->format('d M') }}</div></td>
                    <td>{{ $row['actual_calls'] }} / {{ $row['target']->target_calls }} <div class="small text-muted">{{ $row['call_rate'] ?? 0 }}%</div></td>
                    <td>{{ $row['actual_followups'] }} / {{ $row['target']->target_followups }} <div class="small text-muted">{{ $row['followup_rate'] ?? 0 }}%</div></td>
                    <td>{{ $row['actual_applications'] }} / {{ $row['target']->target_applications }} <div class="small text-muted">{{ $row['application_rate'] ?? 0 }}%</div></td>
                    <td>{{ $row['actual_enrollments'] }} / {{ $row['target']->target_enrollments }} <div class="small text-muted">{{ $row['enrollment_rate'] ?? 0 }}%</div></td>
                    <td>{{ $row['script_compliance'] }}%</td>
                    <td><span class="badge bg-{{ $row['band'] === 'needs_coaching' ? 'danger' : ($row['band'] === 'excellent' ? 'success' : 'primary') }}">{{ ucwords(str_replace('_', ' ', $row['band'])) }}</span><div class="small text-muted">{{ $row['overall_rate'] }}%</div></td>
                    <td>
                        <form method="POST" action="{{ route('admission.counsellor-performance.coach', $row['user']) }}" class="d-flex gap-1">
                            @csrf
                            <input type="hidden" name="score_band" value="{{ $row['band'] }}">
                            <input type="hidden" name="action_plan" value="Review daily pipeline, improve follow-up quality, and confirm next-step commitments.">
                            <button class="btn btn-sm btn-outline-primary">Add note</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark">No active counsellor targets are configured</div>
                        <div class="small">Create current-period targets before comparing calls, follow-ups, applications, enrollments, script compliance, and coaching bands.</div>
                        <a href="{{ route('admission.counsellor-desk.index') }}" class="btn btn-sm btn-outline-primary mt-2">Open Counsellor Desk</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-bold">Coaching Notes</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Counsellor</th><th scope="col">Band</th><th scope="col">Action Plan</th><th scope="col">Next Review</th><th scope="col">Status</th></tr></thead>
            <tbody>
            @forelse($dashboard['coaching'] as $note)
                <tr>
                    <td>{{ $note->counsellor?->name }}<div class="small text-muted">By {{ $note->reviewer?->name ?? 'System' }}</div></td>
                    <td>{{ ucwords(str_replace('_', ' ', $note->score_band)) }}</td>
                    <td>{{ Str::limit($note->action_plan, 90) }}</td>
                    <td>{{ optional($note->next_review_at)->format('d M Y') }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($note->status) }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <div class="fw-semibold text-dark">No coaching notes are open yet</div>
                        <div class="small">Use the scorecard action after reviewing low completion, low script compliance, stale follow-ups, or weak conversion.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $dashboard['coaching']->links() }}</div>
</div>
</div>
@endsection
