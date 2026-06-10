@extends('layouts.admin')

@section('title', 'Load Balancing Analysis')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Load Balancing Analysis</h2>
            <p class="text-muted">Analyze and balance teacher workload distribution.</p>
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

    @if (!empty($analysis))
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Load Distribution Stats</h5>
                        <div class="row text-center">
                            <div class="col-3">
                                <strong>{{ $analysis['stats']['avg_load'] }}</strong> hrs<br><small>Average</small>
                            </div>
                            <div class="col-3">
                                <strong>{{ $analysis['stats']['max_load'] }}</strong> hrs<br><small>Max</small>
                            </div>
                            <div class="col-3">
                                <strong>{{ $analysis['stats']['min_load'] }}</strong> hrs<br><small>Min</small>
                            </div>
                            <div class="col-3">
                                <strong>{{ round($analysis['stats']['imbalance'] * 100) }}%</strong><br><small>Imbalance</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Teacher Workload Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Teacher</th>
                                <th>Hours</th>
                                <th>vs Avg</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analysis['teachers'] as $t)
                                <tr>
                                    <td><strong>{{ $t['teacher_name'] }}</strong></td>
                                    <td>{{ round($t['hours'], 1) }}</td>
                                    <td>{{ $t['hours'] > $analysis['stats']['avg_load'] ? '+' : '' }}{{ round($t['hours'] - $analysis['stats']['avg_load'], 1) }}</td>
                                    <td>
                                        @if ($t['hours'] > $analysis['stats']['avg_load'] * 1.3)
                                            <span class="badge bg-danger">Overloaded</span>
                                        @elseif ($t['hours'] < $analysis['stats']['avg_load'] * 0.7)
                                            <span class="badge bg-warning">Underloaded</span>
                                        @else
                                            <span class="badge bg-success">Balanced</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (!empty($analysis['recommendations']))
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Rebalancing Recommendations</h5>
                </div>
                <div class="card-body">
                    @foreach ($analysis['recommendations'] as $rec)
                        <div class="alert alert-info mb-2">
                            <strong>{{ $rec['teacher'] ?? 'Overall' }}:</strong> {{ $rec['action'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="alert alert-info">Select a program and term to view load balance analysis.</div>
    @endif
</div>
@endsection
