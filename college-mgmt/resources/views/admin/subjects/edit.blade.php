@extends('layouts.admin')
@section('title','Edit Subject')
@section('page-title','Edit Subject')
@section('content')
<div class="card" style="max-width:600px">
    <div class="card-header">Edit: {{ $subject->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.subjects.update', $subject) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Department *</label>
                <select name="department_id" class="form-select" required>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$subject->department_id))>{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $subject->name) }}" required></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Code *</label><input type="text" name="code" class="form-control" value="{{ old('code', $subject->code) }}" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Type *</label>
                    <select name="type" class="form-select" required>
                        @foreach(['theory','practical','tutorial'] as $t)<option value="{{ $t }}" @selected(old('type',$subject->type)==$t)>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Credits</label><input type="number" name="credits" class="form-control" value="{{ old('credits', $subject->credits) }}" min="1" max="10"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Hrs/Week</label><input type="number" name="hours_per_week" class="form-control" value="{{ old('hours_per_week', $subject->hours_per_week) }}" min="1" max="20"></div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" @checked(old('is_active', $subject->is_active))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.subjects.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
