@extends('layouts.admin')

@section('title', 'Room Utilization Report')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Room Utilization Report</h2>
            <p class="text-muted">Analyze classroom usage efficiency across the selected program and term.</p>
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

        @if (!empty($summary))
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Utilization Summary</h5>
                        <div class="row text-center">
                            <div class="col-6 col-md-3">
                                <div class="mb-2">
                                    <strong class="d-block" style="font-size: 1.3rem;">{{ $summary['total_rooms'] }}</strong>
                                    <small class="text-muted">Total Rooms</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="mb-2">
                                    <strong class="d-block text-success" style="font-size: 1.3rem;">{{ $summary['fully_utilized'] }}</strong>
                                    <small class="text-muted">Fully Used</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="mb-2">
                                    <strong class="d-block text-warning" style="font-size: 1.3rem;">{{ $summary['under_utilized'] }}</strong>
                                    <small class="text-muted">Under-used</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="mb-2">
                                    <strong class="d-block" style="font-size: 1.3rem;">{{ $summary['avg_utilization'] }}%</strong>
                                    <small class="text-muted">Avg Use</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (!empty($roomStats))
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Detailed Room Utilization</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Room</th>
                                <th scope="col">Type</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Classes</th>
                                <th scope="col">Max Batch</th>
                                <th scope="col">Max Util.</th>
                                <th scope="col">Avg Util.</th>
                                <th scope="col">Status</th>
                                <th scope="col">Batches</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($roomStats as $room)
                                <tr>
                                    <td><strong>{{ $room['room_number'] }}</strong></td>
                                    <td><small>{{ $room['room_type'] ?? 'Standard' }}</small></td>
                                    <td><strong>{{ $room['capacity'] }}</strong></td>
                                    <td>{{ $room['batch_count'] }}</td>
                                    <td>{{ $room['max_batch_size'] }}</td>
                                    <td>
                                        <div style="width: 60px;">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar @if($room['max_utilization'] > 100) bg-danger @elseif($room['max_utilization'] > 90) bg-success @elseif($room['max_utilization'] > 70) bg-info @else bg-secondary @endif"
                                                     role="progressbar"
                                                     style="width: {{ min($room['max_utilization'], 100) }}%"
                                                     aria-valuenow="{{ $room['max_utilization'] }}"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                    <small>{{ $room['max_utilization'] }}%</small>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $room['avg_utilization'] }}%</td>
                                    <td>
                                        @if ($room['status'] === 'over-capacity')
                                            <span class="badge bg-danger">Over-cap</span>
                                        @elseif ($room['status'] === 'fully-utilized')
                                            <span class="badge bg-success">Optimal (>90%)</span>
                                        @elseif ($room['status'] === 'well-utilized')
                                            <span class="badge bg-info">Good (70-90%)</span>
                                        @elseif ($room['status'] === 'moderately-utilized')
                                            <span class="badge bg-warning">Fair (40-70%)</span>
                                        @else
                                            <span class="badge bg-secondary">Low (<40%)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <details>
                                            <summary style="cursor: pointer; color: #0066cc;">Show</summary>
                                            <div class="mt-2">
                                                @foreach ($room['batches'] as $batch)
                                                    <small class="d-block">{{ $batch }}</small>
                                                @endforeach
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No room utilization data available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (!empty($roomStats))
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card bg-success bg-opacity-10">
                        <div class="card-body">
                            <h5 class="card-title text-success">✓ Optimization Recommendations</h5>
                            <ul class="small mb-0">
                                @if ($summary['under_utilized'] > 0)
                                    <li>Consider consolidating classes into fewer rooms ({{ $summary['under_utilized'] }} under-used)</li>
                                @endif
                                @if ($summary['over_capacity'] > 0)
                                    <li>Identify large batches and split into multiple sessions</li>
                                @endif
                                @if ($summary['avg_utilization'] < 60)
                                    <li>Review room allocation strategy — aim for 70-90% utilization</li>
                                @endif
                                @if ($summary['over_capacity'] == 0)
                                    <li>Room allocation is adequate — no capacity violations</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Status Legend</h5>
                            <div class="small">
                                <div class="mb-2">
                                    <strong class="text-success">Optimal (>90%):</strong> Room is well-utilized
                                </div>
                                <div class="mb-2">
                                    <strong class="text-info">Good (70-90%):</strong> Efficient usage
                                </div>
                                <div class="mb-2">
                                    <strong class="text-warning">Fair (40-70%):</strong> Could be better utilized
                                </div>
                                <div class="mb-2">
                                    <strong class="text-secondary">Low (<40%):</strong> Underutilized room
                                </div>
                                <div class="mb-2">
                                    <strong class="text-danger">Over-capacity:</strong> Room too small for assigned batches
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="alert alert-info">
            Select a program and term to view room utilization analysis.
        </div>
    @endif
</div>
@endsection
