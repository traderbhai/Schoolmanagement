@extends('layouts.admin')
@section('title', 'Timetable Entry')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.timetable.index') }}">Timetable</a></li>
    <li class="breadcrumb-item active">Entry #{{ $entry->id }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0 fw-bold">Timetable Entry</h5>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.timetable.edit', $entry) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="{{ route('admin.timetable.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px">
    <div class="card-header bg-transparent fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Entry Details</div>
    <div class="card-body">
        <table class="table table-borderless mb-0" style="font-size:.9rem">
            <tr><th scope="row" class="text-muted" style="width:160px">Subject</th><td>{{ $entry->subject?->name ?? '—' }} <span class="text-muted small">({{ $entry->subject?->code ?? '' }})</span></td></tr>
            <tr><th scope="row" class="text-muted">Teacher</th><td>{{ $entry->teacher?->user?->name ?? '—' }}</td></tr>
            <tr><th scope="row" class="text-muted">Classroom</th><td>{{ $entry->classroom?->name ?? '—' }} {{ $entry->classroom ? '('.$entry->classroom->room_number.')' : '' }}</td></tr>
            <tr><th scope="row" class="text-muted">Day</th><td>{{ ucfirst($entry->day_of_week) }}</td></tr>
            <tr><th scope="row" class="text-muted">Slot</th><td>
                @if($entry->slot)
                    {{ $entry->slot->name }} &mdash; {{ \Carbon\Carbon::parse($entry->slot->start_time)->format('h:i A') }} to {{ \Carbon\Carbon::parse($entry->slot->end_time)->format('h:i A') }}
                @else
                    —
                @endif
            </td></tr>
            <tr><th scope="row" class="text-muted">Semester</th><td>{{ $entry->semester?->name ?? '—' }}</td></tr>
            <tr><th scope="row" class="text-muted">Course</th><td>{{ $entry->course?->name ?? '—' }}</td></tr>
            <tr><th scope="row" class="text-muted">Status</th><td>
                @if($entry->is_active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </td></tr>
        </table>
    </div>
</div>
@endsection
