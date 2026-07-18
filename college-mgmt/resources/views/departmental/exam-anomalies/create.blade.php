@extends('layouts.admin')
@section('title', 'Report Exam Anomaly')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <a href="{{ route('exam-cell.anomalies.index') }}" class="btn btn-outline-secondary btn-sm" aria-label="Back to exam anomalies"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1 class="h4 fw-bold mb-0">Report Exam Anomaly</h1>
            <span class="text-muted small">Log a malpractice or irregularity for investigation</span>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    @if($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                    <form method="POST" action="{{ route('exam-cell.anomalies.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Exam <span class="text-danger">*</span></label>
                            <select aria-label="Exam" name="exam_id" class="form-select @error('exam_id') is-invalid @enderror" required>
                                <option value="">Select Exam</option>
                                @foreach($exams as $e)
                                <option value="{{ $e->id }}" @selected(old('exam_id')==$e->id)>
                                    {{ $e->subject->name ?? $e->name }} — {{ $e->program->name ?? '' }} ({{ $e->exam_date?->format('d M Y') ?? '' }})
                                </option>
                                @endforeach
                            </select>
                            @error('exam_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                            <select aria-label="Student" name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                <option value="">Select Student</option>
                                @foreach($students as $s)
                                <option value="{{ $s->id }}" @selected(old('student_id')==$s->id)>{{ $s->user->name }} ({{ $s->enrollment_number }})</option>
                                @endforeach
                            </select>
                            @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Anomaly Type <span class="text-danger">*</span></label>
                                <select aria-label="Anomaly Type" name="anomaly_type" class="form-select @error('anomaly_type') is-invalid @enderror" required>
                                    <option value="">Select Type</option>
                                    @foreach(['malpractice'=>'Malpractice','absent_without_reason'=>'Absent Without Reason','late_entry'=>'Late Entry','paper_leak'=>'Paper Leak','other'=>'Other'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('anomaly_type')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                @error('anomaly_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Severity <span class="text-danger">*</span></label>
                                <select aria-label="Severity" name="severity" class="form-select @error('severity') is-invalid @enderror" required>
                                    <option value="">Select Severity</option>
                                    @foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'] as $v=>$l)
                                    <option value="{{ $v }}" @selected(old('severity')===$v)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                @error('severity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Action Taken <span class="text-muted fw-normal">(optional)</span></label>
                            <select aria-label="Action Taken" name="action_taken" class="form-select">
                                <option value="">— Not yet decided —</option>
                                @foreach(['none'=>'No Action','warning'=>'Warning Issued','debarred'=>'Student Debarred','cancelled'=>'Exam Cancelled'] as $v=>$l)
                                <option value="{{ $v }}" @selected(old('action_taken')===$v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <textarea aria-label="Exam anomaly description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror" required placeholder="Describe the anomaly in detail...">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger"><i class="bi bi-exclamation-triangle me-1"></i>Log Anomaly</button>
                            <a href="{{ route('exam-cell.anomalies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
