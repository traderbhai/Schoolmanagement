@extends('layouts.student')

@section('title', 'Subject Registration')
@section('page-title', 'Subject Registration')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Subject Registration</li>
@endsection

@section('content')
<div class="mb-4">
    <p class="text-muted mb-0">
        @if($currentTerm)
            Current Term: <strong>{{ $currentTerm->name }}</strong>
        @else
            No current term assigned.
        @endif
    </p>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if(!$student)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>No student profile found for your account. Please contact the academic office.
    </div>
@else
    @unless($canManageSubjects)
        <div class="alert alert-secondary">
            <i class="bi bi-lock me-1"></i>Subject registration changes are locked because your student profile is not active. Current registrations remain visible for history.
        </div>
    @endunless

<div class="row g-4">
    {{-- Enrolled Subjects --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-check-circle me-2 text-success"></i>My Subjects ({{ $enrolledSubjects->count() }})
                </h5>
            </div>
            <div class="card-body p-0">
                @if($enrolledSubjects->isEmpty())
                    <p class="text-muted p-3 mb-0">No subjects registered yet.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($enrolledSubjects as $enrollment)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $enrollment->subject->name ?? '—' }}</div>
                                <div class="small text-muted font-monospace">{{ $enrollment->subject->code ?? '' }}</div>
                            </div>
                            @if(!$canManageSubjects)
                                <span class="badge bg-secondary">Active students only</span>
                            @else
                            <form action="{{ route('student.subjects.drop', $enrollment) }}" method="POST"
                                  onsubmit="return confirm('Drop {{ $enrollment->subject->name ?? 'this subject' }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-x-circle"></i> Drop
                                </button>
                            </form>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Available Subjects --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Available Subjects
                </h5>
            </div>
            <div class="card-body p-0">
                @if($availableSubjects->isEmpty())
                    <p class="text-muted p-3 mb-0">No additional subjects available for your current term.</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($availableSubjects as $subject)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">{{ $subject->name }}</div>
                                <div class="small text-muted font-monospace">{{ $subject->code }}</div>
                            </div>
                            @if(!$canManageSubjects)
                                <span class="badge bg-secondary">Locked</span>
                            @else
                            <form action="{{ route('student.subjects.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-plus-circle"></i> Add
                                </button>
                            </form>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@endif
@endsection
