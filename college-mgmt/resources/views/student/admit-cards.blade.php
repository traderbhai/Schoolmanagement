@extends('layouts.student')
@section('title', 'Admit Cards')
@section('page-title', 'Exam Admit Cards')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Admit Cards</li>
@endsection

@section('content')

@if($upcomingExams->isEmpty())
<div class="card" style="box-shadow:var(--shadow-sm)">
    <div class="card-body text-center py-5">
        <i class="bi bi-journal-x" style="font-size:3rem;color:var(--clr-text-muted)"></i>
        <h5 class="mt-3" style="color:var(--clr-text-muted)">No admit cards available yet</h5>
        <p class="mb-0 mx-auto" style="color:var(--clr-text-muted);font-size:.88rem;max-width:620px">
            Admit cards appear after CoE schedules a future exam, your exam registration is approved, attendance and fee clearance are verified, and the exam is still unpublished. If you expected a card, check exam registration or contact the Exam Cell.
        </p>
    </div>
</div>
@else

<div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-info-circle me-2"></i>
    Download your admit card before each exam. Bring a printed copy and a valid photo ID to the examination hall.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="row g-3">
    @foreach($upcomingExams as $exam)
    @php
        $daysLeft = now()->diffInDays($exam->exam_date, false);
        $urgency = $daysLeft <= 2 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'success');
        $urgencyLabel = $daysLeft <= 0 ? 'Today' : ($daysLeft === 1 ? 'Tomorrow' : "In {$daysLeft} days");
    @endphp
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="fw-bold mb-0">{{ $exam->name }}</h6>
                    <div class="text-muted small">{{ $exam->subject->name ?? 'General' }}</div>
                </div>
                <span class="badge bg-{{ $urgency }}-subtle text-{{ $urgency }} border border-{{ $urgency }}-subtle">
                    {{ $urgencyLabel }}
                </span>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted fw-normal">Date</dt>
                    <dd class="col-7 fw-semibold mb-1">{{ $exam->exam_date->format('D, d M Y') }}</dd>

                    <dt class="col-5 text-muted fw-normal">Time</dt>
                    <dd class="col-7 mb-1">
                        @if($exam->start_time && $exam->end_time)
                            {{ \Carbon\Carbon::parse($exam->start_time)->format('h:i A') }}
                            to
                            {{ \Carbon\Carbon::parse($exam->end_time)->format('h:i A') }}
                        @else
                            <span class="text-muted">TBA</span>
                        @endif
                    </dd>

                    <dt class="col-5 text-muted fw-normal">Venue</dt>
                    <dd class="col-7 mb-1">{{ $exam->classroom->name ?? 'TBA' }}</dd>

                    <dt class="col-5 text-muted fw-normal">Max Marks</dt>
                    <dd class="col-7 mb-0">{{ $exam->total_marks ?? 'Not set' }}</dd>
                </dl>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                <a href="{{ route('student.admit-cards.download', $exam) }}"
                   class="btn btn-sm btn-primary w-100" target="_blank">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Admit Card
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>

@endif
@endsection
