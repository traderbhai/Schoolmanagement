@extends('layouts.admin')
@section('title','Academic Year')
@section('page-title','Academic Year Details')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.academic-years.index') }}">Academic Years</a></li>
    <li class="breadcrumb-item active">View Academic Year</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold">{{ $academicYear->name }}</h5>
                @if($academicYear->is_current)<span class="badge bg-success mb-2">Current Year</span>@endif
                <table class="table table-sm table-borderless small mt-2 mb-0">
                    <tr><td class="text-muted">Year</td><td>{{ $academicYear->start_year }}–{{ $academicYear->end_year }}</td></tr>
                    <tr><td class="text-muted">Start</td><td>{{ $academicYear->start_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">End</td><td>{{ $academicYear->end_date->format('d M Y') }}</td></tr>
                </table>
                <a href="{{ route('admin.academic-years.edit', $academicYear) }}" class="btn btn-sm btn-outline-primary mt-3">Edit</a>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Semesters</span>
                <a href="{{ route('admin.semesters.create') }}" class="btn btn-sm btn-primary">Add Semester</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Number</th><th>Start</th><th>End</th><th>Current</th></tr></thead>
                    <tbody>
                    @forelse($academicYear->semesters as $s)
                    <tr>
                        <td class="fw-semibold">{{ $s->name }}</td>
                        <td>Sem {{ $s->number }}</td>
                        <td>{{ $s->start_date->format('d M Y') }}</td>
                        <td>{{ $s->end_date->format('d M Y') }}</td>
                        <td>@if($s->is_current)<span class="badge bg-success">Current</span>@endif</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No semesters added yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
