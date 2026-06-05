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
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong>Leave Application</strong></div>
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
                            rows="4" placeholder="Describe the reason for leave..." maxlength="1000" required>{{ old('reason') }}</textarea>
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
