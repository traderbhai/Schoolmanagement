@extends('layouts.admin')

@section('title', 'Edit Term Promotion')
@section('page-title', 'Edit Term Promotion')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.term-promotions.update', $termPromotion) }}" method="POST">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <input type="text" class="form-control" value="{{ $termPromotion->student->enrollment_number ?? 'Enrollment number pending' }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Current Term</label>
                        <input type="text" class="form-control" value="{{ $termPromotion->currentTerm->name ?? 'Current term not linked' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CGPA</label>
                        <input type="number" name="cgpa" class="form-control" step="0.01" min="0" max="10" value="{{ old('cgpa', $termPromotion->cgpa) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Attendance (%)</label>
                        <input type="number" name="attendance_percentage" class="form-control" step="0.01" min="0" max="100" value="{{ old('attendance_percentage', $termPromotion->attendance_percentage) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            @foreach(['pending','on_hold'] as $s)
                                <option value="{{ $s }}" {{ old('status',$termPromotion->status)==$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $termPromotion->remarks) }}</textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update Promotion</button>
                    <a href="{{ route('academic.term-promotions.show', $termPromotion) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
