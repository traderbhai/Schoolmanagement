@extends('layouts.admin')
@section('title', "Teacher's Timetable")
@section('page-title', "Teacher's Timetable View")
@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Teacher</label>
                <select aria-label="Teacher" name="teacher_id" class="form-select form-select-sm">
                    <option value="">-- Select Teacher --</option>
                    @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected($t->id == $teacherId)>{{ $t->user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Semester</label>
                <select aria-label="Semester" name="semester_id" class="form-select form-select-sm">
                    <option value="">-- Select Semester --</option>
                    @foreach($semesters as $s)
                    <option value="{{ $s->id }}" @selected($s->id == $semesterId)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">View</button></div>
        </form>
    </div>
</div>
@if($teacherId && $semesterId && count($grid))
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered timetable-grid mb-0">
                <thead><tr>
                    <th scope="col">Slot</th>
                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d)
                    <th scope="col" class="day-header">{{ $d }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($slots as $slot)
                    @if($slot->is_break)
                    <tr class="break-row"><td class="text-center">{{ $slot->name }}</td><td colspan="6" class="text-center">{{ $slot->start_time }}–{{ $slot->end_time }}</td></tr>
                    @else
                    <tr>
                        <td class="text-center">
                            <div class="fw-semibold small">{{ $slot->name }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $slot->start_time }}–{{ $slot->end_time }}</div>
                        </td>
                        @for($day=1;$day<=6;$day++)
                        <td>
                            @if(isset($grid[$day][$slot->id]))
                                @foreach($grid[$day][$slot->id] as $e)
                                    <div class="timetable-cell mb-2">
                                        <div class="subj">
                                            {{ $e->subject?->name ?? 'Subject' }}
                                            @if(($e->duration_slots ?? 1) > 1)
                                                <span class="badge bg-light text-muted border ms-1">{{ $e->duration_slots }} slots</span>
                                            @endif
                                        </div>
                                        @if($e->course_group)
                                            <div class="text-muted" style="font-size:.68rem">{{ $e->course_group->name }}</div>
                                        @endif
                                        @if($e->is_continuation ?? false)
                                            <div class="text-muted" style="font-size:.68rem">Continued session</div>
                                        @endif
                                        <div class="tchr">{{ $e->course?->code ?? '' }}</div>
                                        <span class="room-tag">{{ $e->classroom?->room_number ?? 'Room TBA' }}</span>
                                    </div>
                                @endforeach
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
@elseif($teacherId && $semesterId)
<div class="alert alert-info">No timetable entries for this teacher/semester combination.</div>
@else
<div class="alert alert-secondary">Select a teacher and semester to view their schedule.</div>
@endif
@endsection
