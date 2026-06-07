@extends('layouts.teacher')

@section('title', 'My Mentees')

@section('content')
<div class="container-fluid px-4">

    <h4 class="mb-3"><i class="bi bi-person-heart me-2 text-primary"></i>My Mentees</h4>

    @if($mentees->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-people fs-1 d-block mb-2"></i>
                <p class="mb-0">No mentees assigned to you yet.</p>
                <p class="small">Contact the Program Chair to get mentees assigned.</p>
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
                                    <h6 class="mb-0 fw-bold">{{ $mentee->user->name ?? '—' }}</h6>
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
                                            {{ $attPct !== null ? number_format($attPct, 1) . '%' : 'N/A' }}
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
                                <th>Date</th>
                                <th>Student</th>
                                <th>Topic</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($upcomingMeetings as $meeting)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}
                                    </td>
                                    <td>{{ $meeting->student->user->name ?? '—' }}</td>
                                    <td>{{ $meeting->topic }}</td>
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
