@extends('layouts.teacher')
@section('title', 'Apply for Leave')
@section('page-title', 'Apply for Leave')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('teacher.leaves.index') }}">Leave</a></li>
    <li class="breadcrumb-item active">Apply</li>
@endsection

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-3 small">
                    <div class="col-md-3">
                        <div class="fw-semibold text-dark">1. Choose leave type</div>
                        <div class="text-muted">Use duty leave for institute work and medical leave for health-related absence.</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-semibold text-dark">2. Check date overlap</div>
                        <div class="text-muted">Open pending or approved leave for the same dates will be blocked.</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-semibold text-dark">3. Give review context</div>
                        <div class="text-muted">Mention class, exam, mentoring, or duty impact so reviewers can decide quickly.</div>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-semibold text-dark">4. Track review</div>
                        <div class="text-muted">Pending requests can be cancelled before review; approved or rejected records remain history.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>Leave Application</strong>
                <div class="text-muted small mt-1">Submit only future leave dates. The system calculates total days after submission and keeps the request pending until academic review.</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.leaves.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                        <select name="leave_type" class="form-select @error('leave_type') is-invalid @enderror" required>
                            <option value="">Select type...</option>
                            <option value="casual"    @selected(old('leave_type')=='casual')>Casual Leave</option>
                            <option value="medical"   @selected(old('leave_type')=='medical')>Medical Leave</option>
                            <option value="earned"    @selected(old('leave_type')=='earned')>Earned Leave</option>
                            <option value="duty"      @selected(old('leave_type')=='duty')>Duty Leave</option>
                            <option value="maternity" @selected(old('leave_type')=='maternity')>Maternity Leave</option>
                            <option value="paternity" @selected(old('leave_type')=='paternity')>Paternity Leave</option>
                        </select>
                        @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">From Date <span class="text-danger">*</span></label>
                            <input type="date" name="from_date" class="form-control @error('from_date') is-invalid @enderror"
                                value="{{ old('from_date') }}" min="{{ date('Y-m-d') }}" required>
                            @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">To Date <span class="text-danger">*</span></label>
                            <input type="date" name="to_date" class="form-control @error('to_date') is-invalid @enderror"
                                value="{{ old('to_date') }}" min="{{ date('Y-m-d') }}" required>
                            @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control @error('reason') is-invalid @enderror"
                            rows="4" placeholder="Example: Duty leave for university workshop; classes will be covered by the assigned substitute." maxlength="1000" required>{{ old('reason') }}</textarea>
                        <div class="form-text">Include handover or coverage notes when the leave affects classes, mentoring, exams, or assessment work.</div>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Application</button>
                        <a href="{{ route('teacher.leaves.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
