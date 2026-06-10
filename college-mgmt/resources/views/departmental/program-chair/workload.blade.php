@extends('layouts.admin')

@section('title', 'Faculty Workload Report')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Faculty Workload Report</h2>
            <p class="text-muted">View teacher workload (hours/week) for the selected program and term.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('chair.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <form method="GET" class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="program_id" class="form-label">Program *</label>
                        <select name="program_id" id="program_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Select Program</option>
                            @foreach ($programs as $prog)
                                <option value="{{ $prog->id }}" {{ $selectedProgram?->id == $prog->id ? 'selected' : '' }}>
                                    {{ $prog->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="term_id" class="form-label">Term *</label>
                        <select name="term_id" id="term_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Select Term</option>
                            @foreach ($terms as $term)
                                <option value="{{ $term->id }}" {{ $selectedTerm?->id == $term->id ? 'selected' : '' }}>
                                    {{ $term->name }} ({{ $term->start_date->format('M Y') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($selectedProgram && $selectedTerm)
                        <a href="{{ route('chair.workload.export', ['program_id' => $selectedProgram->id, 'term_id' => $selectedTerm->id]) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-download"></i> Export CSV
                        </a>
                    @endif
                </div>
            </form>
        </div>

        @if (!empty($summary))
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Summary Statistics</h5>
                        <div class="row text-center">
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-primary" style="font-size: 1.5rem;">{{ $summary['total_teachers'] }}</strong>
                                    <small class="text-muted">Total Teachers</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-danger" style="font-size: 1.5rem;">{{ $summary['overloaded_count'] }}</strong>
                                    <small class="text-muted">Overloaded</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-warning" style="font-size: 1.5rem;">{{ $summary['underloaded_count'] }}</strong>
                                    <small class="text-muted">Underloaded</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center small">
                            <div class="col-6">
                                <strong>Avg Load:</strong> {{ $summary['avg_workload'] }} hrs/week
                            </div>
                            <div class="col-6">
                                <strong>Range:</strong> {{ $summary['min_workload'] }} – {{ $summary['max_workload'] }} hrs/week
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (!empty($report))
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Faculty Workload Details</h5>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Teacher Name</th>
                                <th>Sessions</th>
                                <th>Total Hours</th>
                                <th>Weekly Load</th>
                                <th>Subjects</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($report as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['teacher_name'] }}</strong>
                                    </td>
                                    <td>{{ $row['session_count'] }}</td>
                                    <td>{{ $row['total_hours'] }}</td>
                                    <td>
                                        <strong>{{ $row['weekly_load'] }} hrs</strong>
                                    </td>
                                    <td>{{ $row['subject_count'] }}</td>
                                    <td>
                                        @if ($row['status'] === 'overloaded')
                                            <span class="badge bg-danger">⚠️ Overloaded (>18 hrs)</span>
                                        @elseif ($row['status'] === 'underloaded')
                                            <span class="badge bg-warning">ℹ️ Underloaded (<6 hrs)</span>
                                        @else
                                            <span class="badge bg-success">✓ Optimal</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr class="table-light" style="font-size: 0.85rem;">
                                    <td colspan="6">
                                        <details>
                                            <summary style="cursor: pointer; color: #6c757d;">View schedule details</summary>
                                            <div class="mt-2">
                                                @foreach ($row['entries'] as $entry)
                                                    <div class="small">
                                                        <strong>{{ $entry['day_of_week'] }} {{ $entry['slot'] }}:</strong>
                                                        {{ $entry['subject'] }} ({{ $entry['batch'] }}) — {{ $entry['hours'] }}h
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No timetable entries found. Please select a program and term with timetable data.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (!empty($report))
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Legend</h5>
                            <div class="row small">
                                <div class="col-md-3">
                                    <strong>✓ Optimal (6–18 hrs/week):</strong> Balanced workload
                                </div>
                                <div class="col-md-3">
                                    <strong>⚠️ Overloaded (>18 hrs/week):</strong> Consider load balancing
                                </div>
                                <div class="col-md-3">
                                    <strong>ℹ️ Underloaded (<6 hrs/week):</strong> May need additional courses
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
