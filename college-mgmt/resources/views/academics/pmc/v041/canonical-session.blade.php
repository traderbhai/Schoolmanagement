@extends('layouts.admin')
@section('title', 'Canonical Session #' . $item->id)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Canonical Session #{{ $item->id }}</h1>
            <div class="small text-muted">{{ $item->courseGroup?->name }} | {{ $item->subject?->name ?? $item->courseGroup?->subject?->name }} | {{ $item->day_name }} {{ $item->slot?->name }}</div>
        </div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="row g-3 mb-3">
        @foreach([
            ['Status', $item->official_status ?: $item->status],
            ['Faculty', $item->teacher?->user?->name ?? '-'],
            ['Room', $item->classroom?->name ?? $item->classroom?->room_number ?? '-'],
            ['Bridge', $bridge['status'] . ($bridge['entry_id'] ? ' #' . $bridge['entry_id'] : '')],
            ['Members', $member_count],
            ['Attendance Rows', $attendance_count],
        ] as [$label, $value])
            <div class="col-6 col-md-4 col-xl-2"><div class="card shadow-sm h-100"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="fw-semibold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Session Detail</div>
                <div class="card-body small">
                    <div><span class="text-muted">Program:</span> {{ $item->program?->name ?? $item->courseGroup?->program?->name ?? '-' }}</div>
                    <div><span class="text-muted">Batch:</span> {{ $item->batch?->name ?? $item->courseGroup?->batch?->name ?? '-' }}</div>
                    <div><span class="text-muted">Term:</span> {{ $item->term?->name ?? $item->courseGroup?->term?->name ?? '-' }}</div>
                    <div><span class="text-muted">Session:</span> {{ $item->session_type }} #{{ $item->session_index }} | {{ $item->duration_slots }} slot(s)</div>
                    <div><span class="text-muted">Run:</span> {{ $item->generationRun?->title ?? '-' }}</div>
                    <div><span class="text-muted">Version:</span> {{ $item->timetableVersion?->version_number ?? '-' }}</div>
                    <div><span class="text-muted">Locked:</span> {{ $item->is_locked ? 'yes' : 'no' }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 fw-semibold">Solver And Constraint Explanation</div>
                <div class="card-body small">
                    <div><span class="text-muted">Pass:</span> {{ $solver['pass'] ?? '-' }}</div>
                    <div><span class="text-muted">Score:</span> {{ $solver['score'] ?? '-' }}</div>
                    <div><span class="text-muted">Soft:</span> {{ collect($solver['soft_constraints'] ?? [])->take(5)->implode(', ') ?: '-' }}</div>
                    <div><span class="text-muted">Hard:</span> {{ collect($solver['hard_constraints'] ?? [])->take(5)->implode(', ') ?: '-' }}</div>
                    <div><span class="text-muted">Calendar:</span> {{ collect($solver['calendar_exceptions'] ?? [])->take(5)->implode(', ') ?: '-' }}</div>
                    <div><span class="text-muted">Alternatives:</span> {{ collect($solver['placement_alternatives'] ?? [])->take(3)->map(fn($alt) => 'D'.($alt['day'] ?? '-').'/'.($alt['slot_name'] ?? $alt['slot_id'] ?? '-').' '.($alt['score'] ?? ''))->implode(' | ') ?: '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-6">
            <div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Group Members</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Student</th><th scope="col">Status</th></tr></thead><tbody>@forelse($members as $member)<tr><td>{{ $member->student?->user?->name ?? $member->student?->roll_number ?? $member->student_id }}</td><td>{{ $member->status }}</td></tr>@empty<tr><td colspan="2" class="text-muted">No active members.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $members->links() }}</div></div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Change History</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Reason</th></tr></thead><tbody>@forelse($changes as $change)<tr><td>{{ $change->change_type }}</td><td>{{ $change->status }}</td><td class="small">{{ $change->reason }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No change requests.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $changes->links() }}</div></div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Substitutions</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Date</th><th scope="col">Substitute</th><th scope="col">Action</th></tr></thead><tbody>@forelse($substitutions as $substitution)<tr><td>{{ optional($substitution->date)->format('d M Y') }}</td><td>{{ $substitution->substitute?->user?->name ?? '-' }}</td><td>{{ $substitution->action }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No substitutions.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $substitutions->links() }}</div></div>
        </div>
        <div class="col-xl-6">
            <div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Notifications</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th scope="col">Notice</th><th scope="col">Audience</th><th scope="col">Status</th></tr></thead><tbody>@forelse($notifications as $notification)<tr><td>{{ $notification->title }}</td><td>{{ $notification->recipient_type }}</td><td>{{ $notification->status }}</td></tr>@empty<tr><td colspan="3" class="text-muted">No notifications.</td></tr>@endforelse</tbody></table></div><div class="card-footer py-2">{{ $notifications->links() }}</div></div>
        </div>
    </div>
</div>
@endsection
