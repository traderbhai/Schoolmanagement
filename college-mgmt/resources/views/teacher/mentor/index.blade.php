@extends('layouts.teacher')

@section('title', 'My Mentees')

@section('content')
<div class="container-fluid px-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-person-heart me-2 text-primary"></i>My Mentees</h4>
            <div class="text-muted small">Use this desk to review assigned mentees, respond to messages, schedule progress meetings, and spot attendance or result risks.</div>
        </div>
        @if($mentees->isNotEmpty())
            <span class="badge bg-light text-dark border">{{ $mentees->count() }} assigned mentee{{ $mentees->count() === 1 ? '' : 's' }}</span>
        @endif
    </div>
    @if(!empty($profileMissing))
        <div class="alert alert-warning">
            <strong>Teacher profile not linked.</strong>
            Mentoring data cannot be loaded until administration links your login to a teacher profile.
        </div>
    @endif
    @if(!$canManageMentoring)
        <div class="alert alert-warning">
            Messaging and meeting scheduling are locked because this teacher profile is not active.
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">1. Review risk</div>
                    <div class="text-muted">Open mentees with low attendance or unread messages first.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">2. Contact student</div>
                    <div class="text-muted">Send a message or schedule a structured progress meeting.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">3. Track evidence</div>
                    <div class="text-muted">Keep meeting topics and notes visible for program reviews.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">4. Escalate blockers</div>
                    <div class="text-muted">Use Program Chair or PMC when attendance/result risks need intervention.</div>
                </div>
            </div>
        </div>
    </div>

    @if($mentees->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2"></i>
                <p class="mb-0 fw-semibold text-dark">No mentees are assigned to you yet</p>
                <p class="small mb-0">Mentor assignments are managed by the Program Chair or PMC. Once assigned, only your mapped mentees will appear here.</p>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach($mentees as $mentee)
                @php
                    $attPct = $mentee->att_pct;
                    $attColor = 'success';
                    if ($attPct !== null) {
                        if ($attPct < 60)        $attColor = 'danger';
                        elseif ($attPct < 75)    $attColor = 'warning';
                        else                     $attColor = 'success';
                    }
                @endphp
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-start border-4 border-{{ $attColor }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-0 fw-bold">{{ $mentee->user->name ?? 'Student name unavailable' }}</h6>
                                    <div class="text-muted small">{{ $mentee->enrollment_number ?? '' }}</div>
                                </div>
                                @if($mentee->unread > 0)
                                    <span class="badge bg-danger rounded-pill">
                                        <i class="bi bi-chat-dots me-1"></i>{{ $mentee->unread }} new
                                    </span>
                                @endif
                            </div>

                            <div class="mb-2">
                                @if($mentee->program)
                                    <span class="badge bg-secondary">{{ $mentee->program->name }}</span>
                                @endif
                                @if($mentee->batch)
                                    <span class="badge bg-light text-dark border ms-1">{{ $mentee->batch->name }}</span>
                                @endif
                            </div>

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted">Attendance</span>
                                        <span class="fw-semibold text-{{ $attColor }}">
                                            {{ $attPct !== null ? number_format($attPct, 1) . '%' : 'Not marked yet' }}
                                        </span>
                                    </div>
                                    <div class="progress" style="height:6px;">
                                        <div class="progress-bar bg-{{ $attColor }}"
                                             style="width:{{ $attPct ?? 0 }}%"></div>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('teacher.mentor.mentee', $mentee) }}"
                               class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-person-lines-fill me-1"></i> View Mentee
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Upcoming Meetings --}}
    @if($upcomingMeetings->isNotEmpty())
        <h5 class="mb-2"><i class="bi bi-calendar2-check me-2 text-success"></i>Upcoming Meetings</h5>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Student</th>
                                <th scope="col">Topic</th>
                                <th scope="col" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingMeetings as $meeting)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}
                                    </td>
                                    <td>{{ $meeting->student->user->name ?? 'Student name unavailable' }}</td>
                                    <td>{{ $meeting->topic ?: 'Topic not recorded' }}</td>
                                    <td class="text-center">
                                        @php
                                            $statusBadge = match($meeting->status) {
                                                'scheduled'  => 'bg-primary',
                                                'completed'  => 'bg-success',
                                                'cancelled'  => 'bg-danger',
                                                default      => 'bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusBadge }}">
                                            {{ ucfirst($meeting->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
