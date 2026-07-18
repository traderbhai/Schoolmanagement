@extends('layouts.student')
@section('title', 'Request Attendance Condonation')
@section('content')
<div class="container py-4" style="max-width:600px">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('student.condonation.index') }}">Condonation Requests</a></li>
            <li class="breadcrumb-item active">New Request</li>
        </ol>
    </nav>
    <h4 class="fw-semibold mb-4">Request Attendance Condonation</h4>

    @if(empty($lowSubjects))
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>All your subjects have attendance ≥ 75%. No condonation needed.
    </div>
    @else
    <div class="alert alert-warning mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        You can only request condonation for subjects with attendance below 75%.
    </div>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header fw-semibold">Low Attendance Subjects</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th scope="col">Subject</th><th scope="col">Attendance %</th><th scope="col">Absent Sessions</th></tr></thead>
                <tbody>
                    @foreach($lowSubjects as $row)
                    <tr><td>{{ $row['subject']->name }}</td><td class="text-danger fw-semibold">{{ $row['pct'] }}%</td><td>{{ $row['deficit'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('student.condonation.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Select Subject</label>
                    <select aria-label="Subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                        <option value="">— Choose subject —</option>
                        @foreach($lowSubjects as $row)
                        <option value="{{ $row['subject']->id }}" @selected(old('subject_id') == $row['subject']->id)>
                            {{ $row['subject']->name }} ({{ $row['pct'] }}%)
                        </option>
                        @endforeach
                    </select>
                    @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Condonation</label>
                    <textarea aria-label="Explain the reason for your absence (medical, event participation, etc.)" name="reason" rows="5" class="form-control @error('reason') is-invalid @enderror"
                        placeholder="Explain the reason for your absence (medical, event participation, etc.)">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="{{ route('student.condonation.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
