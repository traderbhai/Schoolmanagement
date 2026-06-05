@extends('layouts.admin')
@section('title', 'Program Chair — Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Program Chair Dashboard</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-primary">{{ $activeStudents }}</div>
                <div class="text-muted small">Active Students</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-info">{{ $subjectsThisTerm }}</div>
                <div class="text-muted small">Subjects</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-warning">{{ $examCount }}</div>
                <div class="text-muted small">Exams This Year</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="fs-2 fw-bold text-success">{{ $avgMarks }}</div>
                <div class="text-muted small">Avg Marks</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @foreach($programs as $prog)
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold">{{ $prog->name }} <span class="badge bg-secondary">{{ $prog->code }}</span></h6>
                <p class="text-muted small">Duration: {{ $prog->duration_years }} yrs | {{ $prog->total_terms }} terms</p>
                <div class="d-flex gap-2">
                    <a href="{{ route('chair.students') }}" class="btn btn-sm btn-outline-primary">Students</a>
                    <a href="{{ route('chair.curriculum') }}" class="btn btn-sm btn-outline-secondary">Curriculum</a>
                    <a href="{{ route('chair.exams') }}" class="btn btn-sm btn-outline-warning">Exams</a>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
