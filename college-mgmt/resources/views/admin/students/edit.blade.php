@extends('layouts.admin')
@section('title','Edit Student')
@section('page-title','Edit Student')
@section('content')
<div class="card" style="max-width:720px">
    <div class="card-header">Edit: {{ $student->user->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.update', $student) }}">
            @csrf @method('PUT')
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Full Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $student->user->name) }}" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Email *</label><input type="email" name="email" class="form-control" value="{{ old('email', $student->user->email) }}" required></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Department *</label>
                    <select name="department_id" class="form-select" required>
                        @foreach($departments as $d)<option value="{{ $d->id }}" @selected($d->id==old('department_id',$student->department_id))>{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Course *</label>
                    <select name="course_id" class="form-select" required>
                        @foreach($courses as $c)<option value="{{ $c->id }}" @selected($c->id==old('course_id',$student->course_id))>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Roll Number</label><input type="text" name="roll_number" class="form-control" value="{{ old('roll_number', $student->roll_number) }}"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Current Semester</label><input type="number" name="current_semester" class="form-control" value="{{ old('current_semester', $student->current_semester) }}" min="1" max="12"></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        @foreach(['active','inactive','graduated','dropped'] as $s)<option value="{{ $s }}" @selected(old('status',$student->status)==$s)>{{ ucfirst($s) }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Date of Birth</label><input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', optional($student->date_of_birth)->format('Y-m-d')) }}"></div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">Select</option>
                        @foreach(['male','female','other'] as $g)<option value="{{ $g }}" @selected(old('gender',$student->gender)==$g)>{{ ucfirst($g) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label fw-semibold">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $student->phone) }}"></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Guardian Name</label><input type="text" name="guardian_name" class="form-control" value="{{ old('guardian_name', $student->guardian_name) }}"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Guardian Phone</label><input type="text" name="guardian_phone" class="form-control" value="{{ old('guardian_phone', $student->guardian_phone) }}"></div>
            </div>
            <div class="d-flex gap-2 mt-3"><button type="submit" class="btn btn-primary">Update</button><a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </form>
    </div>
</div>
@endsection
