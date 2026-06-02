@extends('layouts.admin')
@section('title','Edit Classroom')
@section('page-title','Edit Classroom')
@section('content')
<div class="card" style="max-width:620px">
    <div class="card-header">Edit: {{ $classroom->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.classrooms.update', $classroom) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Room Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $classroom->name) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Room Number *</label><input type="text" name="room_number" class="form-control" value="{{ old('room_number', $classroom->room_number) }}" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type *</label>
                    <select name="type" class="form-select" required>
                        @foreach(['lecture','lab','seminar','auditorium'] as $t)<option value="{{ $t }}" @selected(old('type',$classroom->type)==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Capacity *</label><input type="number" name="capacity" class="form-control" value="{{ old('capacity', $classroom->capacity) }}" min="1" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Floor</label><input type="text" name="floor" class="form-control" value="{{ old('floor', $classroom->floor) }}"></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Building</label><input type="text" name="building" class="form-control" value="{{ old('building', $classroom->building) }}"></div>
            <div class="d-flex gap-4 mb-3">
                <div class="form-check"><input type="checkbox" name="has_projector" class="form-check-input" id="proj" value="1" @checked(old('has_projector',$classroom->has_projector))><label class="form-check-label" for="proj">Has Projector</label></div>
                <div class="form-check"><input type="checkbox" name="has_lab" class="form-check-input" id="lab" value="1" @checked(old('has_lab',$classroom->has_lab))><label class="form-check-label" for="lab">Lab Setup</label></div>
                <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="act" value="1" @checked(old('is_active',$classroom->is_active))><label class="form-check-label" for="act">Active</label></div>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.classrooms.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
