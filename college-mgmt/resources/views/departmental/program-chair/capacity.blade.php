@extends('layouts.admin')

@section('title', 'Classroom Capacity Report')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Classroom Capacity Report</h2>
            <p class="text-muted">Validate room capacity vs batch size and identify utilization issues.</p>
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
                </div>
            </form>
        </div>

        @if (!empty($summary) && $summary['total_violations'] > 0)
            <div class="col-md-6">
                <div class="card bg-danger bg-opacity-10">
                    <div class="card-body">
                        <h5 class="card-title text-danger">⚠ Capacity Issues Found</h5>
                        <div class="row text-center">
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-danger" style="font-size: 1.5rem;">{{ $summary['total_violations'] }}</strong>
                                    <small class="text-muted">Violations</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-danger" style="font-size: 1.5rem;">{{ $summary['total_shortage_seats'] }}</strong>
                                    <small class="text-muted">Missing Seats</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="mb-3">
                                    <strong class="d-block text-danger" style="font-size: 1.5rem;">{{ $summary['critical_violations'] }}</strong>
                                    <small class="text-muted">Critical</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @elseif (!empty($summary))
            <div class="col-md-6">
                <div class="card bg-success bg-opacity-10">
                    <div class="card-body">
                        <h5 class="card-title text-success">✓ All Rooms Adequate</h5>
                        <p class="mb-0">No capacity violations found for this term.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (!empty($violations))
        <div class="card border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">⚠ Capacity Violations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Day/Slot</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Batch</th>
                                <th scope="col">Size</th>
                                <th scope="col">Room</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Shortage</th>
                                <th scope="col">Suggested Rooms</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($violations as $violation)
                                <tr class="table-danger">
                                    <td><small><strong>{{ $violation['day_of_week'] }}</strong> {{ $violation['slot'] }}</small></td>
                                    <td>{{ $violation['subject'] }}</td>
                                    <td><strong>{{ $violation['batch_name'] }}</strong></td>
                                    <td><strong class="text-danger">{{ $violation['batch_size'] }}</strong></td>
                                    <td>{{ $violation['room_number'] }}</td>
                                    <td>{{ $violation['room_capacity'] }}</td>
                                    <td><span class="badge bg-danger">-{{ $violation['shortage'] }}</span></td>
                                    <td>
                                        @if (!empty($violation['suggested_rooms']))
                                            <details>
                                                <summary style="cursor: pointer; color: #0066cc;">View alternatives</summary>
                                                <div class="mt-2">
                                                    @foreach ($violation['suggested_rooms'] as $room)
                                                        <div class="small">
                                                            <strong>{{ $room['room_number'] }}</strong>
                                                            (Cap: {{ $room['capacity'] }}, Use: {{ $room['utilization'] }}%)
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @else
                                            <small class="text-muted">None available</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($utilization))
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Room Utilization Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Room</th>
                                <th scope="col">Type</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Batches</th>
                                <th scope="col">Max Batch</th>
                                <th scope="col">Max Util.</th>
                                <th scope="col">Avg Util.</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($utilization as $room)
                                <tr>
                                    <td><strong>{{ $room['room_number'] }}</strong></td>
                                    <td><small>{{ $room['room_type'] ?? 'Standard' }}</small></td>
                                    <td>{{ $room['capacity'] }}</td>
                                    <td>{{ $room['batch_count'] }}</td>
                                    <td>{{ $room['max_batch_size'] }}</td>
                                    <td>
                                        <strong class="@if($room['max_utilization'] > 100) text-danger @elseif($room['max_utilization'] > 90) text-warning @else text-success @endif">
                                            {{ $room['max_utilization'] }}%
                                        </strong>
                                    </td>
                                    <td>{{ $room['avg_utilization'] }}%</td>
                                    <td>
                                        @if ($room['status'] === 'over-capacity')
                                            <span class="badge bg-danger">Over-capacity</span>
                                        @elseif ($room['status'] === 'fully-utilized')
                                            <span class="badge bg-success">Fully Used (>90%)</span>
                                        @elseif ($room['status'] === 'well-utilized')
                                            <span class="badge bg-info">Well Used (70-90%)</span>
                                        @elseif ($room['status'] === 'moderately-utilized')
                                            <span class="badge bg-warning">Moderate (40-70%)</span>
                                        @else
                                            <span class="badge bg-secondary">Under-used (<40%)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if (empty($selectedProgram) || empty($selectedTerm))
        <div class="alert alert-info">
            Select a program and term to view capacity analysis.
        </div>
    @endif
</div>
@endsection
