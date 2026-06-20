@extends('layouts.teacher')
@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">My Profile</li>
@endsection

@section('content')
<div class="card">
    <div class="card-body py-4">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-2 text-warning"><i class="bi bi-person-exclamation"></i></div>
            <div>
                <h5 class="mb-1">Teacher profile not linked</h5>
                <p class="text-muted mb-3">
                    Your login has the Teacher role, but no teacher profile is attached yet. Timetable, mentoring, leave, feedback, and teaching workflows need this profile to identify your assigned courses and students.
                </p>
                <a href="{{ route('teacher.dashboard') }}" class="btn btn-sm btn-outline-primary">Back to dashboard</a>
            </div>
        </div>
    </div>
</div>
@endsection
