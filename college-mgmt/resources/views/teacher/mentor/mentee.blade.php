@extends('layouts.teacher')

@section('title', 'Mentee - ' . ($student->user->name ?? 'Student'))

@section('content')
<div class="container-fluid px-4">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('teacher.mentor.index') }}">My Mentees</a>
            </li>
            <li class="breadcrumb-item active">{{ $student->user->name ?? 'Student name unavailable' }}</li>
        </ol>
    </nav>

    {{-- Student header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4"
             style="width:52px;height:52px;">
            {{ strtoupper(substr($student->user->name ?? 'S', 0, 1)) }}
        </div>
        <div>
            <h4 class="mb-0">{{ $student->user->name ?? 'Student name unavailable' }}</h4>
            <div class="text-muted small">
                {{ $student->enrollment_number ?? '' }}
                @if($student->program)
                    <span class="mx-1">|</span><span class="badge bg-secondary">{{ $student->program->name }}</span>
                @endif
                @if($student->batch)
                    <span class="mx-1">|</span>{{ $student->batch->name }}
                @endif
            </div>
        </div>
    </div>

    @if(!$canManageMentoring)
        <div class="alert alert-warning">
            Messaging and meeting scheduling are locked because this mentor or mentee profile is not active.
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">1. Check recent signals</div>
                    <div class="text-muted">Use attendance and published results to decide the follow-up priority.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">2. Message or meet</div>
                    <div class="text-muted">Send a short check-in or schedule a progress review with a clear topic.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">3. Record evidence</div>
                    <div class="text-muted">Keep notes specific enough for Program Chair, PMC, and Dean review.</div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold text-dark">4. Escalate when needed</div>
                    <div class="text-muted">Escalate repeated absence, poor results, or non-response through academic governance.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        {{-- LEFT COLUMN --}}
        <div class="col-lg-8">

            {{-- Message Thread --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold bg-white">
                    <i class="bi bi-chat-text me-1 text-primary"></i>Messages
                </div>
                <div class="card-body" style="max-height:420px;overflow-y:auto;" id="message-thread">
                    @if($messages->isEmpty())
                        <div class="text-center text-muted py-3">
                            <div class="fw-semibold text-dark mb-1">No mentor messages are recorded yet</div>
                            <div class="small">Start with a short check-in message, ask about attendance or academic blockers, and keep the conversation history here for future reviews.</div>
                        </div>
                    @else
                        @foreach($messages as $msg)
                            @php $isMine = $msg->sender_id === auth()->id(); @endphp
                            <div class="d-flex {{ $isMine ? 'justify-content-end' : 'justify-content-start' }} mb-3">
                                <div style="max-width:75%;">
                                    <div class="rounded-3 px-3 py-2 {{ $isMine ? 'bg-primary text-white' : 'bg-light text-dark' }}">
                                        {{ $msg->message }}
                                    </div>
                                    <div class="text-muted mt-1 {{ $isMine ? 'text-end' : '' }}" style="font-size:0.72rem;">
                                        {{ $msg->sender->name ?? '' }}
                                        <span class="mx-1">|</span>
                                        {{ \Carbon\Carbon::parse($msg->created_at)->format('d M, H:i') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
                <div class="card-footer bg-white">
                    @if($canManageMentoring)
                    <form method="POST" action="{{ route('teacher.mentor.message', $student) }}">
                        @csrf
                        <div class="d-flex gap-2">
                            <textarea name="message" rows="2"
                                      class="form-control @error('message') is-invalid @enderror"
                                      placeholder="Write a message…" required>{{ old('message') }}</textarea>
                            <button type="submit" class="btn btn-primary px-3 align-self-end">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                        @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                    @else
                        <div class="text-muted small">Mentoring message replies are locked for inactive profiles.</div>
                    @endif
                </div>
            </div>

            {{-- Meetings History --}}
            <div class="card shadow-sm">
                <div class="card-header fw-semibold bg-white">
                    <i class="bi bi-calendar2-week me-1 text-success"></i>Meeting History
                </div>
                <div class="card-body p-0">
                    @if($meetings->isEmpty())
                        <div class="text-center text-muted py-3">
                            <div class="fw-semibold text-dark mb-1">No mentor meetings are scheduled or recorded yet</div>
                            <div class="small">Use the schedule form to create the first progress review, then record the agenda and follow-up notes after the discussion.</div>
                        </div>
                    @else
                        <div class="accordion accordion-flush" id="meetings-accordion">
                            @foreach($meetings as $i => $meeting)
                                @php
                                    $statusBadge = match($meeting->status) {
                                        'completed'  => 'bg-success',
                                        'cancelled'  => 'bg-danger',
                                        default      => 'bg-primary',
                                    };
                                @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#meet-{{ $meeting->id }}">
                                            <span class="me-2 fw-semibold">
                                                {{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}
                                            </span>
                                            <span class="text-muted small me-auto">{{ $meeting->topic }}</span>
                                            <span class="badge {{ $statusBadge }} ms-2">
                                                {{ ucfirst($meeting->status) }}
                                            </span>
                                        </button>
                                    </h2>
                                    <div id="meet-{{ $meeting->id }}" class="accordion-collapse collapse">
                                        <div class="accordion-body text-muted small">
                                            {{ $meeting->notes ?: 'No notes recorded.' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- RIGHT COLUMN --}}
        <div class="col-lg-4">

            {{-- Schedule Meeting --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold bg-white">
                    <i class="bi bi-calendar-plus me-1 text-success"></i>Schedule Meeting
                </div>
                <div class="card-body">
                    @if($canManageMentoring)
                    <form method="POST" action="{{ route('teacher.mentor.meeting', $student) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="meeting_date" value="{{ old('meeting_date') }}"
                                   class="form-control form-control-sm @error('meeting_date') is-invalid @enderror"
                                   required>
                            @error('meeting_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Topic <span class="text-danger">*</span></label>
                            <input type="text" name="topic" value="{{ old('topic') }}"
                                   class="form-control form-control-sm @error('topic') is-invalid @enderror"
                                   placeholder="e.g. Academic progress review" required>
                            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-control form-control-sm"
                                      placeholder="Agenda or discussion points (optional)">{{ old('notes') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-calendar-check me-1"></i> Schedule
                        </button>
                    </form>
                    @else
                        <div class="text-muted small">Meeting scheduling is locked for inactive profiles.</div>
                    @endif
                </div>
            </div>

            {{-- Attendance Summary --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold bg-white">
                    <i class="bi bi-bar-chart me-1 text-warning"></i>Attendance by Subject
                </div>
                <div class="card-body p-0">
                    @if($attBySubject->isEmpty())
                        <div class="text-center text-muted py-3 small">
                            <div class="fw-semibold text-dark mb-1">No published attendance is available yet</div>
                            <div>Attendance appears here after classes are marked against the student's published timetable. Use this section to spot subject-wise attendance risk.</div>
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th class="text-center">Present/Total</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attBySubject as $att)
                                    @php
                                        $pct = $att->total > 0 ? round($att->present / $att->total * 100) : 0;
                                        $color = $pct < 75 ? 'text-danger fw-semibold' : 'text-success';
                                    @endphp
                                    <tr>
                                        <td class="small">{{ $att->subject->name ?? 'Subject unavailable' }}</td>
                                        <td class="text-center small">{{ $att->present }}/{{ $att->total }}</td>
                                        <td class="text-center small {{ $color }}">{{ $pct }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Exam Results --}}
            <div class="card shadow-sm">
                <div class="card-header fw-semibold bg-white">
                    <i class="bi bi-mortarboard me-1 text-info"></i>Recent Exam Results
                </div>
                <div class="card-body p-0">
                    @if($results->isEmpty())
                        <div class="text-center text-muted py-3 small">
                            <div class="fw-semibold text-dark mb-1">No published exam results are available yet</div>
                            <div>Only official published marks are shown to mentors. Draft or unpublished results stay hidden until CoE publication.</div>
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th class="text-center">Marks</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($results as $result)
                                    @php
                                        $resPct = $result->exam && $result->exam->total_marks > 0
                                                  ? round($result->marks_obtained / $result->exam->total_marks * 100)
                                                  : null;
                                    @endphp
                                    <tr>
                                        <td class="small">{{ $result->exam->subject->name ?? 'Subject unavailable' }}</td>
                                        <td class="small">{{ $result->exam->name ?? 'Exam unavailable' }}</td>
                                        <td class="text-center small">
                                            {{ $result->marks_obtained }}
                                            @if($result->exam)
                                                /{{ $result->exam->total_marks }}
                                            @endif
                                        </td>
                                        <td class="text-center small">
                                            @if($resPct !== null)
                                                <span class="{{ $resPct < 40 ? 'text-danger' : ($resPct >= 75 ? 'text-success' : '') }}">
                                                    {{ $resPct }}%
                                                </span>
                                            @else
                                                Not calculated
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    // Auto-scroll message thread to bottom
    const thread = document.getElementById('message-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;
</script>
@endpush
@endsection
