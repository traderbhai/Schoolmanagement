@extends('layouts.admin')
@section('title', 'Slot — ' . $timetableSlot->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.timetable-slots.index') }}">Time Slots</a></li>
    <li class="breadcrumb-item active">{{ $timetableSlot->name }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0 fw-bold">{{ $timetableSlot->name }}</h5>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.timetable-slots.edit', $timetableSlot) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="{{ route('admin.timetable-slots.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:520px">
    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-clock me-2 text-primary"></i>Slot Details</div>
    <div class="card-body">
        <table class="table table-borderless mb-0" style="font-size:.9rem">
            <tr><th class="text-muted" style="width:160px">Name</th><td>{{ $timetableSlot->name }}</td></tr>
            <tr><th class="text-muted">Start Time</th><td>{{ \Carbon\Carbon::parse($timetableSlot->start_time)->format('h:i A') }}</td></tr>
            <tr><th class="text-muted">End Time</th><td>{{ \Carbon\Carbon::parse($timetableSlot->end_time)->format('h:i A') }}</td></tr>
            <tr><th class="text-muted">Duration</th><td>
                @php
                    $mins = (\Carbon\Carbon::parse($timetableSlot->end_time)->diffInMinutes(\Carbon\Carbon::parse($timetableSlot->start_time)));
                @endphp
                {{ $mins }} minutes
            </td></tr>
            <tr><th class="text-muted">Type</th><td>
                @if($timetableSlot->is_break)
                    <span class="badge bg-warning text-dark">Break</span>
                @else
                    <span class="badge bg-primary">Class Period</span>
                @endif
            </td></tr>
            <tr><th class="text-muted">Sort Order</th><td>{{ $timetableSlot->sort_order }}</td></tr>
            <tr><th class="text-muted">Status</th><td>
                @if($timetableSlot->is_active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-danger">Inactive</span>
                @endif
            </td></tr>
        </table>
    </div>
</div>
@endsection
