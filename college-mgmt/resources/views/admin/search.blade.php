@extends('layouts.admin')
@section('title','Search Results')
@section('page-title','Search Results')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Search</li>
@endsection
@section('content')
@if(!isset($q) || strlen($q ?? '') < 2)
<div class="alert alert-info">Please enter at least 2 characters to search.</div>
@else
<p class="text-muted mb-4">Results for: <strong>{{ $q }}</strong></p>

@if(isset($students) && $students->count())
<div class="card border-0 mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-header bg-transparent border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Students ({{ $students->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($students as $student)
            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;font-size:.85rem;flex-shrink:0">
                    {{ substr($student->user->name ?? '?', 0, 1) }}
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $student->user->name ?? 'N/A' }}</div>
                    <div class="text-muted small">{{ $student->enrollment_number }} &bull; Semester {{ $student->current_semester }}</div>
                </div>
                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline-primary">View</a>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if(isset($teachers) && $teachers->count())
<div class="card border-0 mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-header bg-transparent border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-person-workspace me-2 text-success"></i>Teachers ({{ $teachers->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($teachers as $teacher)
            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white fw-bold" style="width:36px;height:36px;font-size:.85rem;flex-shrink:0">
                    {{ substr($teacher->user->name ?? '?', 0, 1) }}
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $teacher->user->name ?? 'N/A' }}</div>
                    <div class="text-muted small">{{ $teacher->designation }} &bull; {{ $teacher->employee_id }}</div>
                </div>
                <a href="{{ route('admin.teachers.show', $teacher) }}" class="btn btn-sm btn-outline-success">View</a>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if(isset($notices) && $notices->count())
<div class="card border-0 mb-4" style="box-shadow:var(--shadow-sm)">
    <div class="card-header bg-transparent border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Notices ({{ $notices->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            @foreach($notices as $notice)
            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $notice->title }}</div>
                    <div class="text-muted small">{{ $notice->audience }} &bull; {{ $notice->publish_date?->format('d M Y') }}</div>
                </div>
                <a href="{{ route('admin.notices.show', $notice) }}" class="btn btn-sm btn-outline-warning">View</a>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if((!isset($students) || !$students->count()) && (!isset($teachers) || !$teachers->count()) && (!isset($notices) || !$notices->count()))
<div class="text-center py-5 text-muted">
    <i class="bi bi-search fs-1 d-block mb-3"></i>
    No results found for "{{ $q }}"
</div>
@endif
@endif
@endsection
