@extends('layouts.admin')
@section('title','Edit Semester')
@section('page-title','Edit Semester')
@section('content')
<div class="card" style="max-width:580px">
    <div class="card-header">Edit: {{ $semester->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.semesters.update', $semester) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Academic Year *</label>
                <select name="academic_year_id" class="form-select" required>
                    @foreach($years as $y)<option value="{{ $y->id }}" @selected($y->id==old('academic_year_id',$semester->academic_year_id))>{{ $y->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $semester->name) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Number *</label><input type="number" name="number" class="form-control" value="{{ old('number', $semester->number) }}" min="1" max="12" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col"><label class="form-label fw-semibold">Start Date</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date', $semester->start_date->format('Y-m-d')) }}" required></div>
                <div class="col"><label class="form-label fw-semibold">End Date</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date', $semester->end_date->format('Y-m-d')) }}" required></div>
            </div>
            <div class="mb-3 form-check"><input type="checkbox" name="is_current" class="form-check-input" id="ic" value="1" @checked(old('is_current',$semester->is_current))><label class="form-check-label" for="ic">Set as Current Semester</label></div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.semesters.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
