@extends('layouts.admin')
@section('title', 'Timetable')
@section('page-title', 'Weekly Timetable')
@section('content')

<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Semester</label>
                <select name="semester_id" class="form-select form-select-sm">
                    <option value="">-- Select Semester --</option>
                    @foreach($semesters as $s)
                    <option value="{{ $s->id }}" @selected($s->id == $semesterId)>{{ $s->name }} ({{ $s->academicYear->name }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">-- All Courses --</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" @selected($c->id == $courseId)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm">Filter</button>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('admin.timetable.create') }}" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Add Entry
                </a>
            </div>
        </form>
    </div>
</div>

@if($semesterId && count($grid))
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Weekly Timetable</span>
        <a href="{{ route('admin.reports.timetable', $semesterId) }}" target="_blank" class="btn btn-sm btn-outline-primary" aria-label="Download timetable PDF">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered timetable-grid mb-0">
                <thead>
                    <tr>
                        <th style="min-width:100px">Slot / Day</th>
                        @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d)
                        <th class="day-header">{{ $d }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($slots as $slot)
                    @if($slot->is_break)
                    <tr class="break-row">
                        <td class="text-center fw-semibold">{{ $slot->name }}</td>
                        <td colspan="6" class="text-center">{{ $slot->start_time }} – {{ $slot->end_time }}</td>
                    </tr>
                    @else
                    <tr>
                        <td class="text-center">
                            <div class="fw-semibold small">{{ $slot->name }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $slot->start_time }}–{{ $slot->end_time }}</div>
                        </td>
                        @for($day=1; $day<=6; $day++)
                        <td>
                            @if(isset($grid[$day][$slot->id]))
                                @php $entry = $grid[$day][$slot->id]; @endphp
                                <div class="timetable-cell">
                                    <div class="subj">{{ $entry->subject->name }}</div>
                                    <div class="tchr">{{ $entry->teacher->user->name }}</div>
                                    <div class="mt-1">
                                        <span class="room-tag">{{ $entry->classroom->room_number }}</span>
                                        <span class="ms-1">
                                            <a href="{{ route('admin.timetable.edit', $entry) }}" class="text-primary" style="font-size:.68rem"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route('admin.timetable.destroy', $entry) }}" class="d-inline" onsubmit="return confirm('Remove?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-link p-0 text-danger" style="font-size:.68rem"><i class="bi bi-x-circle"></i></button>
                                            </form>
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </td>
                        @endfor
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@elseif($semesterId)
<div class="alert alert-info">No timetable entries found. <a href="{{ route('admin.timetable.create') }}">Add one.</a></div>
@else
<div class="alert alert-warning">Select a semester to view the timetable.</div>
@endif
@endsection
