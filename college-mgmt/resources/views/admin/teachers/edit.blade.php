@extends('layouts.admin')
@section('title','Edit Teacher')
@section('page-title','Edit Teacher')
@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header">Edit: {{ $teacher->user->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $teacher->user->name) }}" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control" value="{{ old('email', $teacher->user->email) }}" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department *</label>
                    <select name="department_id" class="form-select" required>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$teacher->department_id))>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label fw-semibold">Designation</label><input type="text" name="designation" class="form-control" value="{{ old('designation', $teacher->designation) }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Qualification</label><input type="text" name="qualification" class="form-control" value="{{ old('qualification', $teacher->qualification) }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Specialization</label><input type="text" name="specialization" class="form-control" value="{{ old('specialization', $teacher->specialization) }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $teacher->phone) }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Date of Joining</label><input type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining', optional($teacher->date_of_joining)->format('Y-m-d')) }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Employment Type</label>
                    <select name="employment_type" class="form-select">
                        @foreach(['full_time'=>'Full Time','part_time'=>'Part Time','visiting'=>'Visiting'] as $v=>$l)<option value="{{ $v }}" @selected(old('employment_type',$teacher->employment_type)==$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active'=>'Active','inactive'=>'Inactive','on_leave'=>'On Leave'] as $v=>$l)<option value="{{ $v }}" @selected(old('status',$teacher->status)==$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
