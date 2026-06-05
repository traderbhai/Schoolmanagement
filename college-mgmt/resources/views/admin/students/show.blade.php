@extends('layouts.admin')
@section('title', 'Student Profile')
@section('page-title', 'Student Profile')
@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div></div>
    <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Students</a>
</div>

<div class="row g-3">
    {{-- Left: Profile Card --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center pt-4">
                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                    style="width:80px;height:80px;background:var(--clr-primary);color:white;font-size:2rem;font-weight:700">
                    {{ strtoupper(substr($student->user->name,0,1)) }}
                </div>
                <h5 class="fw-bold mb-0">{{ $student->user->name }}</h5>
                <div class="text-muted" style="font-size:.84rem">{{ $student->user->email }}</div>
                <div class="mt-2">
                    <span class="badge {{ $student->status === 'active' ? 'badge-active' : 'bg-secondary' }}">{{ ucfirst($student->status) }}</span>
                </div>
            </div>
            <div class="card-body border-top p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.84rem">
                    <tr><td class="text-muted ps-3">Enrollment No.</td><td class="fw-semibold pe-3"><code>{{ $student->enrollment_number }}</code></td></tr>
                    <tr><td class="text-muted ps-3">Roll No.</td><td class="pe-3">{{ $student->roll_number ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Course</td><td class="pe-3">{{ $student->course->name }}</td></tr>
                    <tr><td class="text-muted ps-3">Department</td><td class="pe-3">{{ $student->department->name }}</td></tr>
                    <tr><td class="text-muted ps-3">Semester</td><td class="pe-3">Sem {{ $student->current_semester }}</td></tr>
                    <tr><td class="text-muted ps-3">DOB</td><td class="pe-3">{{ optional($student->date_of_birth)->format('d M Y') ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Gender</td><td class="pe-3">{{ ucfirst($student->gender ?? '–') }}</td></tr>
                    <tr><td class="text-muted ps-3">Phone</td><td class="pe-3">{{ $student->phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Guardian</td><td class="pe-3">{{ $student->guardian_name ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Guardian Ph.</td><td class="pe-3">{{ $student->guardian_phone ?? '–' }}</td></tr>
                    <tr><td class="text-muted ps-3">Admitted</td><td class="pe-3">{{ optional($student->admission_date)->format('d M Y') ?? '–' }}</td></tr>
                </table>
            </div>
            <div class="card-body border-top">
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-pencil me-1"></i>Edit Profile</a>
            </div>
        </div>
    </div>

    {{-- Right: Tabs --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs card-header-tabs ms-0" id="studentTabs">
                    <li class="nav-item">
                        <a class="nav-link active px-4" data-bs-toggle="tab" href="#tab-enrollments">
                            <i class="bi bi-book me-1"></i>Enrollments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4" data-bs-toggle="tab" href="#tab-results">
                            <i class="bi bi-clipboard-check me-1"></i>Exam Results
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-4" data-bs-toggle="tab" href="#tab-fees">
                            <i class="bi bi-cash-coin me-1"></i>Fee History
                        </a>
                    </li>
                </ul>
            </div>
            <div class="tab-content">
                {{-- Enrollments Tab --}}
                <div class="tab-pane fade show active" id="tab-enrollments">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Subject</th><th>Code</th><th>Semester</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse($student->enrollments as $e)
                            <tr>
                                <td class="fw-semibold">{{ $e->subject->name }}</td>
                                <td><code>{{ $e->subject->code }}</code></td>
                                <td>{{ $e->semester->name }}</td>
                                <td><span class="badge {{ $e->status === 'active' ? 'badge-active' : 'bg-secondary' }}">{{ ucfirst($e->status) }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4">
                                <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-book"></i></div><div class="text-muted">No enrollments yet.</div></div>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Exam Results Tab --}}
                <div class="tab-pane fade" id="tab-results">
                    <div class="card-body">
                        @if($student->enrollments->pluck('semester')->unique('id')->count())
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            @foreach($student->enrollments->pluck('semester')->unique('id') as $sem)
                            <a href="{{ route('admin.reports.grade-card', [$student->id, $sem->id]) }}" target="_blank" class="btn btn-sm btn-outline-primary" aria-label="Download grade card for {{ $sem->name }}">
                                <i class="bi bi-file-earmark-pdf me-1"></i>{{ $sem->name }}
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div class="card-body p-0 border-top">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Exam</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Result</th></tr></thead>
                            <tbody>
                            @forelse($student->examResults->take(10) as $r)
                            <tr>
                                <td class="fw-semibold">{{ $r->exam->name }}</td>
                                <td>{{ $r->exam->subject->name }}</td>
                                <td>{{ $r->is_absent ? 'Absent' : ($r->marks_obtained . '/' . $r->exam->total_marks) }}</td>
                                <td>{{ $r->grade ?? '–' }}</td>
                                <td>
                                    @if($r->is_absent)<span class="badge bg-secondary">Absent</span>
                                    @elseif($r->passed)<span class="badge badge-active">Pass</span>
                                    @else<span class="badge badge-danger">Fail</span>@endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5">
                                <div class="empty-state py-4"><div class="empty-icon"><i class="bi bi-clipboard"></i></div><div class="text-muted">No results yet.</div></div>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Fee History Tab --}}
                <div class="tab-pane fade" id="tab-fees">
                    <div class="card-body d-flex justify-content-end">
                        <a href="{{ route('admin.fees.collect') }}?student_id={{ $student->id }}" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-plus-circle me-1"></i>Collect Payment
                        </a>
                    </div>
                    <div class="card-body p-0 border-top">
                        @if($student->feePayments->count())
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Receipt #</th><th>Fee Type</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                            @foreach($student->feePayments()->with('feeStructure')->latest('payment_date')->get() as $payment)
                            <tr>
                                <td style="font-size:.8rem">{{ $payment->receipt_number ?? '—' }}</td>
                                <td style="font-size:.8rem">{{ optional($payment->feeStructure)->fee_type ?? '—' }}</td>
                                <td class="fw-semibold text-success" style="font-size:.8rem">₹{{ number_format($payment->amount_paid) }}</td>
                                <td style="font-size:.8rem">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                                <td class="text-capitalize" style="font-size:.8rem">{{ $payment->payment_method }}</td>
                                <td><span class="badge {{ $payment->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">{{ ucfirst($payment->status) }}</span></td>
                                <td>
                                    <a href="{{ route('admin.reports.fee-receipt', $payment->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.72rem" aria-label="Download receipt PDF">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @else
                        <div class="empty-state py-4">
                            <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                            <div class="text-muted">No payment records found.</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
