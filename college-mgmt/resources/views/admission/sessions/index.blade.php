@extends('layouts.admin')

@section('title', 'Selection Sessions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Selection Sessions</h2>
        <small class="text-muted">Manage GD / PI / WAT and other selection rounds</small>
    </div>
    <a href="{{ route('admission.sessions.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Create Session
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="program_id" class="form-select form-select-sm">
                    <option value="">All Programs</option>
                    @foreach($programs as $p)
                        <option value="{{ $p->id }}" @selected(request('program_id') == $p->id)>{{ $p->abbreviation }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="step_type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($stepTypes as $key => $label)
                        <option value="{{ $key }}" @selected(request('step_type') == $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    @foreach(['scheduled','ongoing','completed','cancelled'] as $s)
                        <option value="{{ $s }}" @selected(request('status') == $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

{{-- Upcoming Sessions --}}
<h5 class="fw-bold mb-3"><i class="bi bi-calendar-event me-2 text-primary"></i>Upcoming Sessions</h5>
@if($upcoming->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>No upcoming sessions found.
        <a href="{{ route('admission.sessions.create') }}" class="btn btn-sm btn-primary mt-2">Schedule a Session</a>
    </div>
@else
<div class="row g-3 mb-4">
    @foreach($upcoming as $session)
    <div class="col-md-6 col-lg-4">
        @include('admission.sessions._card', ['session' => $session])
    </div>
    @endforeach
</div>
@endif

{{-- Past Sessions --}}
<h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-secondary"></i>Past Sessions</h5>
@if($past->isEmpty())
    <div class="text-muted py-3">No past sessions.</div>
@else
<div class="row g-3">
    @foreach($past as $session)
    <div class="col-md-6 col-lg-4">
        @include('admission.sessions._card', ['session' => $session])
    </div>
    @endforeach
</div>
@endif
@endsection
