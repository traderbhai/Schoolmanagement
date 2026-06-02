@extends('layouts.admin')
@section('title','My Dashboard')
@section('page-title','Student Dashboard')
@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Name</div><div class="fw-bold">{{ $student->user->name }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Enrollment No.</div><div class="fw-bold">{{ $student->enrollment_number }}</div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Current Semester</div><div class="fw-bold">Semester {{ $student->current_semester }}</div></div></div></div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>My Weekly Timetable @if($currentSemester)<span class="badge bg-light text-dark ms-2">{{ $currentSemester->name }}</span>@endif</div>
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
                                <td>@if(isset($grid[$day][$slot->id]))<div class="timetable-cell"><div class="subj">{{ $grid[$day][$slot->id]->subject->name }}</div><div class="tchr">{{ $grid[$day][$slot->id]->teacher->user->name }}</div><span class="room-tag">{{ $grid[$day][$slot->id]->classroom->room_number }}</span></div>@endif</td>
                                @endfor
                            </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4">No timetable available for this semester.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-megaphone me-2"></i>Notices</div>
            <div class="list-group list-group-flush">
                @forelse($notices as $n)
                <div class="list-group-item py-2"><div class="fw-semibold small">{{ $n->title }}</div><div class="text-muted" style="font-size:.72rem">{{ $n->publish_date->format('d M Y') }}</div></div>
                @empty
                <div class="list-group-item text-muted small text-center py-3">No notices.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
