@extends('layouts.admin')

@section('title', 'Soft Constraint Audit')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Soft Constraint Audit</h2>
            <p class="text-muted">Analyze scheduling quality based on soft constraints (preferences, spread, etc).</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('chair.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <form method="GET" class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
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

                        <div class="col-md-4 mb-3">
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

                        <div class="col-md-4 mb-3">
                            <label for="batch_id" class="form-label">Batch (Optional)</label>
                            <select name="batch_id" id="batch_id" class="form-select" onchange="this.form.submit()">
                                <option value="">All Batches</option>
                                @foreach ($batches as $batch)
                                    <option value="{{ $batch->id }}" {{ $selectedBatch?->id == $batch->id ? 'selected' : '' }}>
                                        {{ $batch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if (!empty($auditResult))
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Issue Summary</h5>
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="mb-2">
                                    <strong class="d-block" style="font-size: 1.5rem;">{{ $auditResult['summary']['total_issues'] }}</strong>
                                    <small class="text-muted">Total Issues</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="mb-2">
                                    <strong class="d-block text-danger" style="font-size: 1.5rem;">{{ $auditResult['summary']['errors'] }}</strong>
                                    <small class="text-muted">Errors</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="mb-2">
                                    <strong class="d-block text-warning" style="font-size: 1.5rem;">{{ $auditResult['summary']['warnings'] }}</strong>
                                    <small class="text-muted">Warnings</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="mb-2">
                                    <strong class="d-block text-info" style="font-size: 1.5rem;">{{ $auditResult['summary']['info'] }}</strong>
                                    <small class="text-muted">Info</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (!empty($auditResult['issues']))
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Issues Found</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @foreach ($auditResult['issues'] as $issue)
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div style="flex: 1;">
                                        <h6 class="mb-1">
                                            @if ($issue['severity'] === 'error')
                                                <span class="badge bg-danger">ERROR</span>
                                            @elseif ($issue['severity'] === 'warning')
                                                <span class="badge bg-warning text-dark">WARNING</span>
                                            @else
                                                <span class="badge bg-info">INFO</span>
                                            @endif
                                            {{ ucfirst(str_replace('_', ' ', $issue['type'])) }}
                                        </h6>
                                        <p class="mb-1 text-muted">{{ $issue['message'] }}</p>
                                        <small class="text-secondary">
                                            💡 <strong>Recommendation:</strong> {{ $issue['recommendation'] }}
                                        </small>
                                    </div>
                                    @if (isset($issue['subject']))
                                        <div style="margin-left: 20px; text-align: right; flex-shrink: 0;">
                                            <small class="d-block"><strong>{{ $issue['subject'] ?? $issue['teacher'] ?? '' }}</strong></small>
                                            @if (isset($issue['batch']))
                                                <small class="d-block text-muted">{{ $issue['batch'] }}</small>
                                            @endif
                                            @if (isset($issue['day']))
                                                <small class="d-block text-muted">{{ $issue['day'] }} {{ $issue['slot'] ?? '' }}</small>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Soft Constraints Explained</h5>
                            <div class="row small">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Subject Spread:</strong> Classes for same subject should be spread across 2-3 days (not all on same day)
                                    </div>
                                    <div class="mb-3">
                                        <strong>Lab Grouping:</strong> Lab sessions should be back-to-back in consecutive slots
                                    </div>
                                    <div class="mb-3">
                                        <strong>Teacher Availability:</strong> Assignments should respect teacher's marked unavailable times
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Break Times:</strong> No classes should be scheduled during marked break periods
                                    </div>
                                    <div class="mb-3">
                                        <strong>Teacher Gaps:</strong> Large gaps (>2 hours) between teacher's classes create inefficiency
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-success">
                ✓ No soft constraint issues found! Your timetable scheduling is well-optimized.
            </div>
        @endif
    @else
        <div class="alert alert-info">
            Select a program and term to audit soft constraints.
        </div>
    @endif
</div>
@endsection
