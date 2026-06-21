@extends('layouts.admin')

@section('title', 'Automation Simulation')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Automation Simulation</h3>
            <div class="text-muted small">Preview rule matches, due schedules, and conflicts before automation changes admission records.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admission.automation-simulation.run') }}">
                @csrf
                <button class="btn btn-primary btn-sm">Run Due</button>
            </form>
            <a class="btn btn-outline-success btn-sm" href="{{ route('admission.v037.exports', 'automation') }}">Export</a>
        </div>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Safe automation workflow:</strong> simulate first, check matched records and conflicts, then run due schedules only when the action and audience are expected.
    </div>

    <form method="POST" action="{{ route('admission.automation-simulation.simulate') }}" class="card border-0 shadow-sm mb-3">
        @csrf
        <div class="card-body">
            <label class="form-label small" for="automation_id">Automation rule</label>
            <div class="d-flex flex-wrap gap-2">
                <select id="automation_id" class="form-select" name="automation_id" required @disabled($automations->isEmpty())>
                    @forelse($automations as $automation)
                        <option value="{{ $automation->id }}">{{ $automation->name }}</option>
                    @empty
                        <option value="">No automation rules configured</option>
                    @endforelse
                </select>
                <button class="btn btn-outline-primary" @disabled($automations->isEmpty())>Simulate</button>
            </div>
            @if($automations->isEmpty())
                <div class="form-text">Create active automation rules before using simulation or scheduled automation runs.</div>
            @endif
        </div>
    </form>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Simulations</span>
                    <span class="small text-muted">{{ $simulations->count() }} recent</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Automation simulations">
                        <thead class="table-light">
                            <tr>
                                <th>Trigger</th>
                                <th>Matched</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($simulations as $simulation)
                                <tr>
                                    <td>{{ ucwords(str_replace('_', ' ', $simulation->trigger ?: 'trigger not captured')) }}</td>
                                    <td>{{ $simulation->matched_count }}</td>
                                    <td>{{ $simulation->created_at?->diffForHumans() ?? 'Time not captured' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No automation simulations have been run yet</div>
                                        <div class="small">Run a simulation to preview which leads, applicants, reminders, or assessment records would be affected.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Conflicts</span>
                    <span class="small text-muted">{{ $conflicts->count() }} recent</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Automation conflicts">
                        <thead class="table-light">
                            <tr>
                                <th>Conflict</th>
                                <th>Severity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($conflicts as $conflict)
                                <tr>
                                    <td>{{ $conflict->conflict_key ?: 'Conflict key pending' }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $conflict->severity ?: 'severity pending')) }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $conflict->status ?: 'open')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No automation conflicts are open</div>
                                        <div class="small">Conflicts appear when rules would make competing assignments, stage changes, reminders, or communication actions.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
