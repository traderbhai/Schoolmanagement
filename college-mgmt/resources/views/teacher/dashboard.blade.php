@extends('layouts.teacher')
@section('title','Teacher Dashboard')
@section('page-title','Teacher Dashboard')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Name</div><div class="fw-bold">{{ $teacher->user->name }}</div><div class="text-muted small">{{ $teacher->designation }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Employee ID</div><div class="fw-bold">{{ $teacher->employee_id }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Weekly Load</div><div class="fw-bold">{{ $weeklyLoad }} periods/week</div></div></div></div>
</div>
<div class="card">
    <div class="card-header"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>My Weekly Schedule</div>
    <div class="card-body p-0">
        @if(count($grid))
        <div class="table-responsive">
            <table class="table table-bordered timetable-grid mb-0">
                <thead><tr><th>Slot</th>@foreach(['Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<th class="day-header">{{ $d }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($slots as $slot)
                    @if($slot->is_break)
                    <tr class="break-row"><td class="text-center">{{ $slot->name }}</td><td colspan="6" class="text-center">{{ $slot->start_time }}–{{ $slot->end_time }}</td></tr>
                    @else
                    <tr>
                        <td class="text-center"><div class="fw-semibold small">{{ $slot->name }}</div><div class="text-muted" style="font-size:.7rem">{{ $slot->start_time }}-{{ $slot->end_time }}</div></td>
                        @for($day=1;$day<=6;$day++)
                        <td>@if(isset($grid[$day][$slot->id]))<div class="timetable-cell"><div class="subj">{{ $grid[$day][$slot->id]->subject->name }}</div><div class="tchr">{{ $grid[$day][$slot->id]->course->code }}</div><span class="room-tag">{{ $grid[$day][$slot->id]->classroom->room_number }}</span></div>@endif</td>
                        @endfor
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">No timetable entries found.</div>
        @endif
    </div>
</div>
@endsection
