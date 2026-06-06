@extends('layouts.admin')

@section('title', 'Add Scholarship')
@section('page-title', 'Add Scholarship')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.scholarships.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Select Student</option>
                            @foreach($students as $student)
                                <option value="{{ $student->id }}">{{ $student->enrollment_number }}</option>
                            @endforeach
                        </select>
                        @error('student_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Scholarship Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="merit" {{ old('type')=='merit'?'selected':'' }}>Merit</option>
                            <option value="need_based" {{ old('type')=='need_based'?'selected':'' }}>Need Based</option>
                            <option value="category" {{ old('type')=='category'?'selected':'' }}>Category</option>
                            <option value="other" {{ old('type')=='other'?'selected':'' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Percentage (%)</label>
                        <input type="number" name="percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('percentage', 0) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fixed Amount (₹)</label>
                        <input type="number" name="fixed_amount" class="form-control" step="0.01" min="0" value="{{ old('fixed_amount') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid From</label>
                        <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valid To</label>
                        <input type="date" name="valid_to" class="form-control" value="{{ old('valid_to') }}">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Scholarship</button>
                    <a href="{{ route('academic.scholarships.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
