@extends('layouts.student')
@section('title', 'Submit Marks Appeal')
@section('page-title', 'Submit Marks Appeal')

@section('content')
<div class="container py-4" style="max-width:640px">
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-megaphone me-2"></i>New Marks Appeal</div>
        <div class="card-body">
            <form method="POST" action="{{ route('student.appeals.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Result <span class="text-danger">*</span></label>
                    <select name="exam_result_id" class="form-select @error('exam_result_id') is-invalid @enderror" required>
                        <option value="">Choose a result</option>
                        @foreach($results as $result)
                        <option value="{{ $result->id }}" {{ old('exam_result_id')==$result->id?'selected':'' }}>
                            {{ $result->exam->subject->name ?? '?' }} - {{ $result->exam->name ?? '?' }}
                            ({{ $result->marks_obtained }}/{{ $result->exam->total_marks ?? '?' }})
                        </option>
                        @endforeach
                    </select>
                    @error('exam_result_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Marks Claimed <span class="text-danger">*</span></label>
                    <input type="number" name="marks_claimed" step="0.5" min="0"
                           class="form-control @error('marks_claimed') is-invalid @enderror"
                           value="{{ old('marks_claimed') }}" required>
                    @error('marks_claimed')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                    <select name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                        <option value="">Select reason</option>
                        <option value="totalling_error" {{ old('reason')=='totalling_error'?'selected':'' }}>Totalling / Calculation Error</option>
                        <option value="unmarked_question" {{ old('reason')=='unmarked_question'?'selected':'' }}>Unmarked Question</option>
                        <option value="wrong_entry" {{ old('reason')=='wrong_entry'?'selected':'' }}>Wrong Entry in System</option>
                        <option value="other" {{ old('reason')=='other'?'selected':'' }}>Other</option>
                    </select>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description" rows="4"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="Describe why you believe the marks are incorrect...">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit Appeal</button>
                    <a href="{{ route('student.appeals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
