@extends('layouts.admin')
@section('title','Student Profile')
@section('page-title','Student Profile')
@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;font-size:2rem">
                    {{ strtoupper(substr($student->user->name, 0, 1)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $student->user->name }}</h5>
                <div class="text-muted small">{{ $student->user->email }}</div>
                <span class="badge {{ $student->status === 'active' ? 'bg-success' : 'bg-secondary' }} mt-2">{{ ucfirst($student->status) }}</span>
            </div>
            <div class="card-body border-top">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted">Enrollment No.</td><td class="fw-semibold">{{ $student->enrollment_number }}</td></tr>
                    <tr><td class="text-muted">Roll No.</td><td>{{ $student->roll_number ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Course</td><td>{{ $student->course->name }}</td></tr>
                    <tr><td class="text-muted">Department</td><td>{{ $student->department->name }}</td></tr>
                    <tr><td class="text-muted">Semester</td><td>Sem {{ $student->current_semester }}</td></tr>
                    <tr><td class="text-muted">DOB</td><td>{{ optional($student->date_of_birth)->format('d M Y') ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Gender</td><td>{{ ucfirst($student->gender ?? '–') }}</td></tr>
                    <tr><td class="text-muted">Phone</td><td>{{ $student->phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Guardian</td><td>{{ $student->guardian_name ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Guardian Ph.</td><td>{{ $student->guardian_phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Admitted</td><td>{{ optional($student->admission_date)->format('d M Y') ?? '–' }}</td></tr>
                </table>
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-outline-primary mt-3 w-100">Edit Profile</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">Enrolled Subjects</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Subject</th><th>Semester</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($student->enrollments as $e)
                    <tr>
                        <td>{{ $e->subject->name }} <span class="text-muted small">({{ $e->subject->code }})</span></td>
                        <td>{{ $e->semester->name }}</td>
                        <td><span class="badge {{ $e->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($e->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">No enrollments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Recent Exam Results</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Result</th></tr></thead>
                    <tbody>
                    @forelse($student->examResults->take(10) as $r)
                    <tr>
                        <td>{{ $r->exam->name }}</td>
                        <td>{{ $r->exam->subject->name }}</td>
                        <td>{{ $r->is_absent ? 'Absent' : ($r->marks_obtained . '/' . $r->exam->total_marks) }}</td>
                        <td>{{ $r->grade ?? '–' }}</td>
                        <td>
                            @if($r->is_absent)<span class="badge bg-secondary">Absent</span>
                            @elseif($r->passed)<span class="badge bg-success">Pass</span>
                            @else<span class="badge bg-danger">Fail</span>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No results yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cash-coin me-2 text-success"></i>Fee Payment History</span>
        <a href="{{ route('admin.fees.collect') }}?student_id={{ $student->id }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i>Collect Payment
        </a>
    </div>
    <div class="card-body p-0">
        @if($student->feePayments->count())
        <table class="table table-sm mb-0">
            <thead><tr>
                <th>Receipt #</th><th>Fee Type</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th>
            </tr></thead>
            <tbody>
            @foreach($student->feePayments()->with('feeStructure')->latest('payment_date')->get() as $payment)
            <tr>
                <td class="small">{{ $payment->receipt_number ?? '—' }}</td>
                <td class="small">{{ optional($payment->feeStructure)->fee_type ?? '—' }}</td>
                <td class="small fw-semibold">₹{{ number_format($payment->amount_paid) }}</td>
                <td class="small">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                <td class="small text-capitalize">{{ $payment->payment_method }}</td>
                <td><span class="badge bg-{{ $payment->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($payment->status) }}</span></td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <div class="text-center text-muted py-3 small">No payment records found.</div>
        @endif
    </div>
</div>
@endsection
