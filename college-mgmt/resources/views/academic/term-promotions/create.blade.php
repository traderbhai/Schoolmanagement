@extends('layouts.admin')

@section('title', 'Generate Term Promotions')
@section('page-title', 'Generate Term Promotions')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <form action="{{ route('academic.term-promotions.generate') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Term</label>
                        <select aria-label="Term" name="term_id" class="form-select" required>
                            <option value="">Select Term</option>
                            @foreach($terms as $term)
                                <option value="{{ $term->id }}" {{ old('term_id')==$term->id?'selected':'' }}>{{ $term->name }}</option>
                            @endforeach
                        </select>
                        @error('term_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CGPA Threshold</label>
                        <input aria-label="Cgpa Threshold" type="number" name="cgpa_threshold" class="form-control" step="0.01" min="0" max="10" value="{{ old('cgpa_threshold', 2.0) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Attendance Threshold (%)</label>
                        <input aria-label="Attendance Threshold" type="number" name="attendance_threshold" class="form-control" step="0.01" min="0" max="100" value="{{ old('attendance_threshold', 75) }}">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Generate Promotions</button>
                    <a href="{{ route('academic.term-promotions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
