@extends('layouts.admin')

@section('title', 'Timetable Analytics Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Timetable Analytics Dashboard</h2>
            <p class="text-muted">Comprehensive overview of workload, capacity, and load balance metrics.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('chair.dashboard') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>

    <form method="GET" class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <label for="program_id" class="form-label">Program</label>
                    <select name="program_id" id="program_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Select</option>
                        @foreach ($programs as $p)
                            <option value="{{ $p->id }}" {{ $selectedProgram?->id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="term_id" class="form-label">Term</label>
                    <select name="term_id" id="term_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Select</option>
                        @foreach ($terms as $t)
                            <option value="{{ $t->id }}" {{ $selectedTerm?->id == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </form>

    @if (!empty($dashboardData))
        <!-- Workload Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Teachers</h6>
                        <h3 class="mb-0">{{ $dashboardData['workload']['total_teachers'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Overloaded</h6>
                        <h3 class="mb-0 text-danger">{{ $dashboardData['workload']['overloaded_count'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Underloaded</h6>
                        <h3 class="mb-0 text-warning">{{ $dashboardData['workload']['underloaded_count'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Avg Workload</h6>
                        <h3 class="mb-0">{{ round($dashboardData['workload']['avg_workload'] ?? 0, 1) }}<small> hrs/week</small></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Capacity Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Violations</h6>
                        <h3 class="mb-0 text-danger">{{ $dashboardData['capacity']['total_violations'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Shortage Seats</h6>
                        <h3 class="mb-0 text-danger">{{ $dashboardData['capacity']['shortage_seats'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Affected Batches</h6>
                        <h3 class="mb-0">{{ $dashboardData['capacity']['affected_batches'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Critical Issues</h6>
                        <h3 class="mb-0 text-danger">{{ $dashboardData['capacity']['critical_violations'] ?? 0 }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Load Balance Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Load Imbalance</h6>
                        <h3 class="mb-0">{{ round(($dashboardData['loadBalance']['imbalance'] ?? 0) * 100) }}%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Avg Load</h6>
                        <h3 class="mb-0">{{ round($dashboardData['loadBalance']['avg_load'] ?? 0, 1) }}<small> hrs</small></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Max Load</h6>
                        <h3 class="mb-0">{{ round($dashboardData['loadBalance']['max_load'] ?? 0, 1) }}<small> hrs</small></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Min Load</h6>
                        <h3 class="mb-0">{{ round($dashboardData['loadBalance']['min_load'] ?? 0, 1) }}<small> hrs</small></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Overall Statistics</h5>
                        <div class="row text-center mt-4">
                            <div class="col-md-6">
                                <div>
                                    <strong>{{ $dashboardData['totalEntries'] ?? 0 }}</strong><br>
                                    <small>Total Timetable Entries</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <strong>{{ $selectedProgram?->name ?? 'N/A' }}</strong><br>
                                    <small>Program</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Related Reports</h5>
                        <div class="btn-group" role="group">
                            <a href="{{ route('chair.workload', ['program_id' => $selectedProgram?->id, 'term_id' => $selectedTerm?->id]) }}" class="btn btn-outline-primary">Faculty Workload</a>
                            <a href="{{ route('chair.capacity', ['program_id' => $selectedProgram?->id, 'term_id' => $selectedTerm?->id]) }}" class="btn btn-outline-primary">Capacity Analysis</a>
                            <a href="{{ route('chair.load-balance', ['program_id' => $selectedProgram?->id, 'term_id' => $selectedTerm?->id]) }}" class="btn btn-outline-primary">Load Balance</a>
                            <a href="{{ route('chair.constraints', ['program_id' => $selectedProgram?->id, 'term_id' => $selectedTerm?->id]) }}" class="btn btn-outline-primary">Soft Constraints</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">Select a program and term to view analytics.</div>
    @endif
</div>
@endsection
