@extends('layouts.admin')
@section('title','Classroom Details')
@section('page-title','Classroom Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.classrooms.index') }}">Classrooms</a></li>
    <li class="breadcrumb-item active">View Classroom</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold">{{ $classroom->name }}</h5>
                <span class="badge bg-secondary mb-2">{{ $classroom->room_number }}</span>
                <table class="table table-sm table-borderless mb-0 small">
                    <tr><td class="text-muted">Type</td><td>{{ ucfirst($classroom->type) }}</td></tr>
                    <tr><td class="text-muted">Capacity</td><td>{{ $classroom->capacity }} seats</td></tr>
                    <tr><td class="text-muted">Building</td><td>{{ $classroom->building ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Floor</td><td>{{ $classroom->floor ?? '–' }}</td></tr>
                    <tr><td class="text-muted">Projector</td><td>{{ $classroom->has_projector ? 'Yes' : 'No' }}</td></tr>
                    <tr><td class="text-muted">Lab Setup</td><td>{{ $classroom->has_lab ? 'Yes' : 'No' }}</td></tr>
                    <tr><td class="text-muted">Utilization</td><td><span class="badge {{ $utilization > 70 ? 'bg-danger' : ($utilization > 40 ? 'bg-warning text-dark' : 'bg-success') }}">{{ $utilization }}%</span></td></tr>
                </table>
                <a href="{{ route('admin.classrooms.edit', $classroom) }}" class="btn btn-sm btn-outline-primary mt-3">Edit</a>
            </div>
        </div>
    </div>
</div>
@endsection
