@extends('layouts.admin')
@section('title','Edit Slot')
@section('page-title','Edit Time Slot')
@section('content')
<div class="card" style="max-width:500px">
    <div class="card-header">Edit: {{ $timetableSlot->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable-slots.update', $timetableSlot) }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $timetableSlot->name) }}" required></div>
            <div class="row g-3 mb-3">
                <div class="col"><label class="form-label fw-semibold">Start Time</label><input type="time" name="start_time" class="form-control" value="{{ old('start_time', $timetableSlot->start_time) }}" required></div>
                <div class="col"><label class="form-label fw-semibold">End Time</label><input type="time" name="end_time" class="form-control" value="{{ old('end_time', $timetableSlot->end_time) }}" required></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $timetableSlot->sort_order) }}"></div>
            <div class="mb-3 form-check"><input type="checkbox" name="is_break" class="form-check-input" id="is_break" value="1" @checked(old('is_break', $timetableSlot->is_break))><label class="form-check-label" for="is_break">Break slot</label></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.timetable-slots.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
