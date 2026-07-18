@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">{{ $scopeLabel }} &middot; Review allocated courses, groups, timetable impact, and pending PMC requests.</div>
        </div>
        <div class="d-flex gap-1">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('student.pmc-elective-choices') }}">Elective Choices</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('student.pmc-timetable') }}">My Timetable</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        @foreach([
            ['Allocated', $metrics['allocated']],
            ['Waitlisted', $metrics['waitlisted']],
            ['Groups', $metrics['grouped']],
            ['Classes', $metrics['classes']],
            ['Open Requests', $metrics['open_requests']],
        ] as [$label, $value])
            <div class="col-6 col-lg">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <a class="h5 mb-0 text-decoration-none" href="#{{ str($label)->slug() }}">{{ $value }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm mb-3" id="allocated">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Basket Status</label>
                    <select aria-label="Status" class="form-select form-select-sm" name="status">
                        <option value="">All statuses</option>
                        @foreach(['draft', 'allocated', 'conflict_review', 'approved', 'locked', 'dropped'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Course Type</label>
                    <select aria-label="Type" class="form-select form-select-sm" name="type">
                        <option value="">All types</option>
                        @foreach(['core', 'elective', 'lab', 'tutorial', 'repeat', 'backlog', 'improvement', 'audit', 'open_elective'] as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ str($type)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('student.pmc-course-basket') }}">Reset</a>
                </div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All allocated and waitlisted courses' }}</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 fw-semibold">Allocated Courses</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Course</th><th scope="col">Term</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Group/Section</th><th scope="col">Flags</th></tr></thead>
                        <tbody>
                            @forelse($allocations as $allocation)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $allocation->subject?->name ?? $allocation->subject?->code ?? 'Unassigned subject' }}</div>
                                        <div class="small text-muted">{{ $allocation->subject?->code }} &middot; {{ $allocation->allocation_source }}</div>
                                    </td>
                                    <td>{{ $allocation->term?->name ?? '-' }}</td>
                                    <td>{{ str($allocation->allocation_type)->headline() }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $allocation->waitlisted ? 'warning' : ($allocation->basket_status === 'approved' ? 'success' : 'secondary') }}">
                                            {{ $allocation->waitlisted ? 'Waitlisted' : str($allocation->basket_status)->headline() }}
                                        </span>
                                    </td>
                                    <td>
                                        @forelse($allocation->groupMemberships as $membership)
                                            <div>{{ $membership->courseGroup?->name }}</div>
                                            <div class="small text-muted">{{ str($membership->courseGroup?->group_type ?? 'group')->headline() }} &middot; {{ $membership->status }}</div>
                                        @empty
                                            <span class="text-muted">Not grouped yet</span>
                                        @endforelse
                                    </td>
                                    <td class="small">
                                        @foreach(($allocation->validation_flags ?? []) as $flag)
                                            <span class="badge text-bg-light border">{{ str($flag)->headline() }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No course basket allocation is available for your account yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer py-2">{{ $allocations->links() }}</div>
            </div>

            <div class="card shadow-sm" id="classes">
                <div class="card-header py-2 fw-semibold">Timetable Preview</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th scope="col">Day</th><th scope="col">Slot</th><th scope="col">Course Group</th><th scope="col">Faculty</th><th scope="col">Room</th></tr></thead>
                        <tbody>
                            @forelse($timetableItems as $item)
                                <tr>
                                    <td>{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$item->day_of_week] ?? 'Day ' . $item->day_of_week }}</td>
                                    <td>{{ $item->slot?->name ?? 'Unassigned slot' }}</td>
                                    <td><div class="fw-semibold">{{ $item->courseGroup?->name }}</div><div class="small text-muted">{{ $item->courseGroup?->subject?->name }}</div></td>
                                    <td>{{ $item->teacher?->user?->name ?? $item->teacher?->employee_id ?? 'Unassigned faculty' }}</td>
                                    <td>{{ $item->classroom?->name ?? $item->classroom?->room_number ?? 'Unassigned room' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No scheduled classes are available for your allocated groups yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <form method="POST" action="{{ route('student.pmc-course-basket.acknowledge') }}" class="card shadow-sm mb-3">
                @csrf
                <div class="card-header py-2 fw-semibold">Submit Response</div>
                <div class="card-body vstack gap-2">
                    <label class="form-label small mb-0">Course</label>
                    <select aria-label="Student Course Allocation" class="form-select form-select-sm" name="student_course_allocation_id">
                        <option value="">General basket/timetable response</option>
                        @foreach($allocationOptions as $option)
                            <option value="{{ $option->id }}">{{ $option->subject?->code }} - {{ $option->subject?->name }} ({{ str($option->basket_status)->headline() }})</option>
                        @endforeach
                    </select>
                    <label class="form-label small mb-0">Response Type</label>
                    <select aria-label="Acknowledgement Type" class="form-select form-select-sm" name="acknowledgement_type" required>
                        <option value="allocation_review">Allocation reviewed</option>
                        <option value="timetable_acknowledgement">Timetable acknowledged</option>
                        <option value="objection">Raise objection</option>
                        <option value="add_drop_request">Add/drop request</option>
                        <option value="waitlist_followup">Waitlist follow-up</option>
                    </select>
                    <input aria-label="Short reason, if applicable" class="form-control form-control-sm" name="reason" placeholder="Short reason, if applicable">
                    <textarea aria-label="Explain your request or confirmation" class="form-control form-control-sm" name="student_note" rows="4" placeholder="Explain your request or confirmation"></textarea>
                    <button class="btn btn-sm btn-primary">Submit To PMC</button>
                </div>
            </form>

            <div class="card shadow-sm" id="open-requests">
                <div class="card-header py-2 fw-semibold">Acknowledgements And Requests</div>
                <div class="list-group list-group-flush">
                    @forelse($acknowledgements as $ack)
                        <div class="list-group-item py-2">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="fw-semibold">{{ str($ack->acknowledgement_type)->headline() }}</div>
                                <span class="badge text-bg-{{ in_array($ack->status, ['approved', 'resolved', 'acknowledged']) ? 'success' : (in_array($ack->status, ['rejected', 'cancelled']) ? 'danger' : 'warning') }}">{{ str($ack->status)->headline() }}</span>
                            </div>
                            <div class="small text-muted">{{ $ack->allocation?->subject?->name ?? 'General basket response' }} &middot; {{ optional($ack->submitted_at)->format('d M Y H:i') }}</div>
                            @if($ack->student_note)<div class="small mt-1">{{ $ack->student_note }}</div>@endif
                            @if($ack->pmc_note)<div class="small text-muted mt-1">PMC: {{ $ack->pmc_note }}</div>@endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">No acknowledgement or request has been submitted yet.</div>
                    @endforelse
                </div>
                <div class="card-footer py-2">{{ $acknowledgements->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
