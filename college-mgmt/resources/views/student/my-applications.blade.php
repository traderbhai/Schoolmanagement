@extends('layouts.student')
@section('title', 'My Applications')
@section('page-title', 'My Applications')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('student.placements') }}">Placements</a></li>
    <li class="breadcrumb-item active">My Applications</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0 fw-bold">My Placement Applications</h5>
        <div class="text-muted small">Track status, packages, and next steps for each drive.</div>
    </div>
    <a href="{{ route('student.placements') }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-briefcase me-1"></i>Browse Drives
    </a>
</div>

@include('student.partials.placement-applications-table', ['myApplications' => $myApplications])
@endsection
