@extends('layouts.admin')
@section('title', $title)
@section('content')
@php($selectorOptions = $selectorOptions ?? [])
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">{{ $scopeLabel }} · {{ $groupCount }} assigned group{{ $groupCount === 1 ? '' : 's' }}</div>
        </div>
        @if($mode === 'pmc')
            @include('academics.pmc.v041.partials.nav')
        @elseif($mode === 'student')
            <div class="d-flex gap-1">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('student.pmc-course-basket') }}">My Course Basket</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('student.pmc-elective-choices') }}">Elective Choices</a>
            </div>
        @endif
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Day</label>
                    <select class="form-select form-select-sm" name="day_of_week">
                        <option value="">All days</option>
                        @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $day => $label)
                            <option value="{{ $day }}" @selected(request('day_of_week') == $day)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($mode === 'pmc')
                    <div class="col-md-2"><label class="form-label small">Program</label><select class="form-select form-select-sm" name="program_id"><option value="">All programs</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->code ?: $program->name }} - {{ $program->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Batch</label><select class="form-select form-select-sm" name="batch_id"><option value="">All batches</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}" @selected((string) request('batch_id') === (string) $batch->id)>{{ $batch->code ?: $batch->name }} - {{ $batch->program?->code }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Term</label><select class="form-select form-select-sm" name="term_id"><option value="">All terms</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}" @selected((string) request('term_id') === (string) $term->id)>{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Group</label><select class="form-select form-select-sm" name="course_group_id"><option value="">All groups</option>@foreach($selectorOptions['courseGroups'] ?? [] as $group)<option value="{{ $group->id }}" @selected((string) request('course_group_id') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Faculty</label><select class="form-select form-select-sm" name="teacher_id"><option value="">All faculty</option>@foreach($selectorOptions['teachers'] ?? [] as $teacher)<option value="{{ $teacher->id }}" @selected((string) request('teacher_id') === (string) $teacher->id)>{{ $teacher->user?->name ?? $teacher->employee_id }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Room</label><select class="form-select form-select-sm" name="classroom_id"><option value="">All rooms</option>@foreach($selectorOptions['classrooms'] ?? [] as $room)<option value="{{ $room->id }}" @selected((string) request('classroom_id') === (string) $room->id)>{{ $room->name ?? $room->room_number }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Subject</label><select class="form-select form-select-sm" name="subject_id"><option value="">All subjects</option>@foreach($selectorOptions['subjects'] ?? [] as $subject)<option value="{{ $subject->id }}" @selected((string) request('subject_id') === (string) $subject->id)>{{ $subject->code ?: $subject->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small">Session</label><select class="form-select form-select-sm" name="session_type"><option value="">All types</option>@foreach(['lecture' => 'Lecture', 'tutorial' => 'Tutorial', 'lab' => 'Lab', 'practical' => 'Practical', 'seminar' => 'Seminar'] as $value => $label)<option value="{{ $value }}" @selected(request('session_type') === $value)>{{ $label }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}">Reset</a>
                </div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All assigned timetable records' }}</div>
        </div>
    </div>

    @if($mode === 'pmc')
        <div class="card shadow-sm mb-3">
            <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">Master Parallel Slot Board</div>
                    <div class="small text-muted">Each day/slot can contain many official sessions when faculty, room, and student cohorts do not overlap.</div>
                </div>
                <span class="badge text-bg-light border">{{ ($parallelSlotGroups ?? collect())->sum('session_count') }} official session{{ (($parallelSlotGroups ?? collect())->sum('session_count') === 1) ? '' : 's' }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 130px">Day</th>
                            <th style="width: 150px">Slot</th>
                            <th>Parallel Official Sessions</th>
                            <th style="width: 120px">Rooms</th>
                            <th style="width: 120px">Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($parallelSlotGroups ?? collect()) as $slotGroup)
                            <tr>
                                <td>{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$slotGroup['day_of_week']] ?? 'Day ' . $slotGroup['day_of_week'] }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $slotGroup['slot']?->name ?? 'Slot ' . $slotGroup['slot_id'] }}</div>
                                    <div class="small text-muted">{{ $slotGroup['session_count'] }} parallel</div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($slotGroup['sessions'] as $session)
                                            <div class="border rounded p-2 bg-light">
                                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-semibold">{{ $session->courseGroup?->name ?? 'Unassigned group' }}</div>
                                                        <div class="small text-muted">{{ $session->courseGroup?->subject?->name ?? $session->subject?->name ?? 'Unassigned subject' }} | {{ $session->session_type }} | {{ $session->duration_slots }} slot{{ (int) $session->duration_slots === 1 ? '' : 's' }}</div>
                                                    </div>
                                                    <span class="badge text-bg-success">Official</span>
                                                </div>
                                                <div class="small mt-1">Faculty: {{ $session->teacher?->user?->name ?? 'Unassigned faculty' }} | Room: {{ $session->classroom?->name ?? $session->classroom?->room_number ?? 'Unassigned room' }}</div>
                                                <div class="small text-muted">Version #{{ $session->timetableVersion?->version_number ?? '-' }} | Group ID {{ $session->course_group_id }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td>{{ $slotGroup['rooms'] }}</td>
                                <td>{{ $slotGroup['faculty'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No published PMC official sessions match this scope yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header py-2 fw-semibold">Scheduled Group Classes</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Day</th><th>Slot</th><th>Course Group</th><th>Subject</th><th>Faculty</th><th>Room</th><th>State</th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$item->day_of_week] ?? 'Day ' . $item->day_of_week }}</td>
                            <td>{{ $item->slot?->name ?? 'Unassigned slot' }}</td>
                            <td><div class="fw-semibold">{{ $item->courseGroup?->name }}</div><div class="small text-muted">{{ $item->courseGroup?->group_type }}</div></td>
                            <td>{{ $item->courseGroup?->subject?->name ?? $item->courseGroup?->subject?->code ?? 'Unassigned subject' }}</td>
                            <td>{{ $item->teacher?->user?->name ?? $item->teacher?->employee_id ?? 'Unassigned faculty' }}</td>
                            <td>{{ $item->classroom?->name ?? $item->classroom?->room_number ?? 'Unassigned room' }}</td>
                            <td>{{ $item->is_locked ? 'locked' : $item->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No PMC group timetable records are available for this scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2">{{ $items->links() }}</div>
    </div>
</div>
@endsection
