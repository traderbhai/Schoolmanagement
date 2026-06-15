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
                    <div class="col-md-2"><label class="form-label small">Term</label><select class="form-select form-select-sm" name="term_id"><option value="">All terms</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}" @selected((string) request('term_id') === (string) $term->id)>{{ $term->name }} - {{ $term->program?->code }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-primary">Filter</button>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ url()->current() }}">Reset</a>
                </div>
            </form>
            <div class="small text-muted mt-2">Visible filter summary: {{ count(request()->query()) ? http_build_query(request()->query()) : 'All assigned timetable records' }}</div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2 fw-semibold">Scheduled Group Classes</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Day</th><th>Slot</th><th>Course Group</th><th>Subject</th><th>Faculty</th><th>Room</th><th>State</th></tr></thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$item->day_of_week] ?? 'Day ' . $item->day_of_week }}</td>
                            <td>{{ $item->slot?->name ?? 'Slot #' . $item->timetable_slot_id }}</td>
                            <td><div class="fw-semibold">{{ $item->courseGroup?->name }}</div><div class="small text-muted">{{ $item->courseGroup?->group_type }}</div></td>
                            <td>{{ $item->courseGroup?->subject?->name ?? 'Subject #' . $item->courseGroup?->subject_id }}</td>
                            <td>{{ $item->teacher?->user?->name ?? 'Faculty #' . $item->teacher_id }}</td>
                            <td>{{ $item->classroom?->name ?? 'Room #' . $item->classroom_id }}</td>
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
