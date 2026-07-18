@extends('layouts.admin')
@section('title', 'Edit Slot')
@section('page-title', 'Edit Time Slot')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.timetable-slots.index') }}">Time Slots</a></li>
    <li class="breadcrumb-item active">Edit Time Slot</li>
@endsection

@section('content')

<div class="card" style="max-width:520px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Slot — {{ $timetableSlot->name }}</span>
        <a href="{{ route('admin.timetable-slots.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.timetable-slots.update', $timetableSlot) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input aria-label="Name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $timetableSlot->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                    <input aria-label="Start Time" type="time" name="start_time" class="form-control" value="{{ old('start_time', $timetableSlot->start_time) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                    <input aria-label="End Time" type="time" name="end_time" class="form-control" value="{{ old('end_time', $timetableSlot->end_time) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sort Order</label>
                    <input aria-label="Sort Order" type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $timetableSlot->sort_order) }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_break" class="form-check-input" id="is_break" value="1" @checked(old('is_break', $timetableSlot->is_break))>
                        <label class="form-check-label" for="is_break">Break slot</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Slot</button>
                <a href="{{ route('admin.timetable-slots.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
