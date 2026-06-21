@extends('layouts.student')
@section('title', 'My Mentor')
@section('page-title', 'My Mentor')

@section('content')
<div class="container-fluid py-3" style="max-width:960px">

    @if(!$student->mentor)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-person-badge fs-1 d-block mb-2"></i>
            <div class="fw-semibold text-dark mb-1">No faculty mentor is assigned yet</div>
            <div class="small">Your department or program office assigns mentors. Until then, use your course pages, notices, or department office for academic support.</div>
        </div>
    </div>
    @else

    {{-- Mentor Info --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--clr-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <div class="fw-semibold fs-6">{{ $student->mentor->name }}</div>
                <div class="text-muted small">Faculty Mentor · {{ $student->mentor->department->name ?? '' }}</div>
                @if($student->mentor->email ?? false)
                <a href="mailto:{{ $student->mentor->email }}" class="small">{{ $student->mentor->email }}</a>
                @endif
            </div>
        </div>
    </div>

    @if(!$canUseMentorWorkflow)
        <div class="alert alert-warning">
            Mentor messaging and meeting requests are locked because this student profile is not active.
        </div>
    @endif

    <div class="row g-4">
        {{-- Message Thread --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header fw-semibold"><i class="bi bi-chat-dots me-2"></i>Messages</div>
                <div class="card-body p-0" style="max-height:360px;overflow-y:auto;">
                    @forelse($messages as $msg)
                    @php $isMe = $msg->sender_id === auth()->id(); @endphp
                    <div class="px-3 py-2 border-bottom {{ $isMe ? '' : 'bg-light' }}">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small">{{ $isMe ? 'You' : ($msg->sender->name ?? 'Mentor') }}</span>
                            <span class="text-muted" style="font-size:.72rem">{{ $msg->created_at->format('d M, h:i A') }}</span>
                        </div>
                        <p class="mb-0 small">{!! nl2br(e($msg->message)) !!}</p>
                    </div>
                    @empty
                    <div class="p-3 text-muted small">
                        <div class="fw-semibold text-dark mb-1">No mentor messages are recorded yet</div>
                        <div>Start with a short update about attendance, course progress, blockers, or support you need.</div>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer">
                    @if($canUseMentorWorkflow)
                    <form method="POST" action="{{ route('student.mentor.message') }}">
                        @csrf
                        <div class="d-flex gap-2">
                            <textarea name="message" rows="2" class="form-control form-control-sm @error('message') is-invalid @enderror"
                                placeholder="Write a message...">{{ old('message') }}</textarea>
                            <button class="btn btn-sm btn-primary align-self-end">Send</button>
                        </div>
                        @error('message')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </form>
                    @else
                        <div class="text-muted small">Mentor replies are locked for inactive student profiles.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Meetings --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-calendar-plus me-2"></i>Request Meeting</div>
                <div class="card-body">
                    @if($canUseMentorWorkflow)
                    <form method="POST" action="{{ route('student.mentor.meeting') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Preferred Date <span class="text-danger">*</span></label>
                            <input type="date" name="meeting_date" class="form-control form-control-sm @error('meeting_date') is-invalid @enderror"
                                   min="{{ now()->addDay()->toDateString() }}" value="{{ old('meeting_date') }}" required>
                            @error('meeting_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Topic / Agenda <span class="text-danger">*</span></label>
                            <input type="text" name="topic" class="form-control form-control-sm @error('topic') is-invalid @enderror"
                                   value="{{ old('topic') }}" placeholder="e.g. Academic guidance, Career advice" required>
                            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Send Request</button>
                    </form>
                    @else
                        <div class="text-muted small">Meeting requests are locked for inactive student profiles.</div>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header fw-semibold"><i class="bi bi-calendar3 me-2"></i>Meeting History</div>
                <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
                    @forelse($meetings as $meeting)
                    @php $badge = match($meeting->status) { 'confirmed'=>'success','completed'=>'secondary','cancelled'=>'danger',default=>'warning' }; @endphp
                    <div class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-semibold">{{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d M Y') }}</span>
                            <span class="badge bg-{{ $badge }}">{{ ucfirst($meeting->status) }}</span>
                        </div>
                        <p class="mb-0 small text-muted">{{ $meeting->topic }}</p>
                        @if($meeting->notes)
                        <p class="mb-0" style="font-size:.75rem;color:#555">{{ Str::limit($meeting->notes, 80) }}</p>
                        @endif
                    </div>
                    @empty
                    <div class="list-group-item text-muted small py-3 text-center">
                        <div class="fw-semibold text-dark mb-1">No mentor meetings are scheduled or recorded yet</div>
                        <div>Request a meeting when you need academic guidance, intervention support, or career/course advice.</div>
                    </div>
                    @endforelse
                </div>
                @if($meetings->hasPages())
                <div class="card-footer">{{ $meetings->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @endif
</div>
@endsection
