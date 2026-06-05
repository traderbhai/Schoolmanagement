@extends('layouts.admin')
@section('title', 'Edit Classroom')
@section('page-title', 'Edit Classroom')
@section('content')

<div class="card" style="max-width:640px">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Classroom — {{ $classroom->name }}</span>
        <a href="{{ route('admin.classrooms.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.classrooms.update', $classroom) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label class="form-label">Room Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $classroom->name) }}" required>
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Room Number <span class="text-danger">*</span></label>
                    <input type="text" name="room_number" class="form-control @error('room_number') is-invalid @enderror" value="{{ old('room_number', $classroom->room_number) }}" required>
                    @error('room_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" required>
                        @foreach(['lecture','lab','seminar','auditorium'] as $t)<option value="{{ $t }}" @selected(old('type',$classroom->type)==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Capacity <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" class="form-control" value="{{ old('capacity', $classroom->capacity) }}" min="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Floor</label>
                    <input type="text" name="floor" class="form-control" value="{{ old('floor', $classroom->floor) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Building</label>
                    <input type="text" name="building" class="form-control" value="{{ old('building', $classroom->building) }}">
                </div>
                <div class="col-12">
                    <label class="form-label d-block">Facilities & Status</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input type="checkbox" name="has_projector" class="form-check-input" id="proj" value="1" @checked(old('has_projector',$classroom->has_projector))>
                            <label class="form-check-label" for="proj"><i class="bi bi-projector me-1"></i>Has Projector</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="has_lab" class="form-check-input" id="lab" value="1" @checked(old('has_lab',$classroom->has_lab))>
                            <label class="form-check-label" for="lab"><i class="bi bi-pc-display me-1"></i>Lab Setup</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="act" value="1" @checked(old('is_active',$classroom->is_active))>
                            <label class="form-check-label" for="act">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Update Room</button>
                <a href="{{ route('admin.classrooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
