@extends('layouts.student')
@section('title', 'Exam Registration')
@section('page-title', 'Exam Registration')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">

    @if(!$eligibilityIssues->isEmpty())
    <div class="alert alert-warning mb-4">
        <strong><i class="bi bi-exclamation-triangle me-1"></i>Eligibility Issues</strong>
        <ul class="mb-0 mt-1 ps-3">
            @foreach($eligibilityIssues as $issue)
            <li class="small">{{ $issue }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($exams->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
            No upcoming exams available for registration.
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-pencil-square me-2"></i>Upcoming Exams</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Exam</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Attendance</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($exams as $exam)
                    @php
                        $reg = $registrations[$exam->id] ?? null;
                        $attPct = $attendancePct[$exam->subject_id] ?? null;
                        $attOk = $attPct === null || $attPct >= 75;
                        $feeOk = !$hasDues;
                        $canReg = $attOk && $feeOk && !$reg;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $exam->title }}</td>
                        <td class="text-muted small">{{ $exam->subject->name ?? '—' }}</td>
                        <td class="small">{{ $exam->exam_date ? \Carbon\Carbon::parse($exam->exam_date)->format('d M Y') : '—' }}</td>
                        <td>
                            @if($attPct !== null)
                                <span class="badge {{ $attPct >= 75 ? 'bg-success' : 'bg-danger' }}">{{ round($attPct) }}%</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($hasDues)
                                <span class="badge bg-danger">Dues Pending</span>
                            @else
                                <span class="badge bg-success">Clear</span>
                            @endif
                        </td>
                        <td>
                            @if($reg)
                                @php $badge = match($reg->status) { 'approved'=>'success','rejected'=>'danger',default=>'warning' }; @endphp
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($reg->status) }}</span>
                            @else
                                <span class="text-muted small">Not registered</span>
                            @endif
                        </td>
                        <td>
                            @if(!$reg && $canReg)
                            <form method="POST" action="{{ route('student.exam-reg.register', $exam) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary">Register</button>
                            </form>
                            @elseif(!$reg && !$attOk)
                                <span class="text-danger small">Att. < 75%</span>
                            @elseif(!$reg && !$feeOk)
                                <span class="text-danger small">Fee dues</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
