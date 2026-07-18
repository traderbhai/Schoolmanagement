@extends('layouts.admin')
@section('title', 'Admission Lead Scoring')

@section('content')
@php
    $bandClass = fn (?string $band) => match ($band) {
        'hot' => 'danger',
        'warm' => 'warning',
        'cold' => 'secondary',
        default => 'secondary',
    };
@endphp

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Lead Scoring</h1>
            <div class="text-muted small">Prioritize admission leads using source quality, urgency, engagement, response speed, and manual priority points.</div>
        </div>
        <a href="{{ route('admission.leads.index') }}" class="btn btn-sm btn-outline-primary">Open Lead Queue</a>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <form method="POST" action="{{ route('admission.scoring.recalculate') }}" class="card border-0 shadow-sm h-100">
                @csrf
                <div class="card-header bg-white fw-semibold">Recalculate Lead Score</div>
                <div class="card-body vstack gap-3">
                    <div>
                        <label class="form-label">Lead</label>
                        <select aria-label="Lead" name="lead_id" class="form-select" required>
                            <option value="">Select lead</option>
                            @foreach($leads as $lead)
                                <option value="{{ $lead->id }}">
                                    {{ $lead->name }} - {{ $lead->program?->name ?? 'No program' }} - {{ str($lead->status)->headline() }} - {{ str($lead->priority ?? 'normal')->headline() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Only active, contactable leads are listed. Archived/lost leads stay in reports.</div>
                    </div>
                    <div>
                        <label class="form-label">Manual priority points</label>
                        <input aria-label="Manual Priority Points" name="manual_priority_points" type="number" class="form-control" value="0" min="-50" max="50">
                        <div class="form-text">Use this for verified context not captured by activity signals.</div>
                    </div>
                    <button class="btn btn-primary w-100">Recalculate Score</button>
                </div>
            </form>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Recent Score History</span>
                    <span class="text-muted small">{{ $scores->count() }} record(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Lead score history">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Lead</th>
                                <th scope="col">Score</th>
                                <th scope="col">Band</th>
                                <th scope="col">Signals</th>
                                <th scope="col">Scored</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($scores as $score)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $score->lead?->name ?? 'Deleted lead' }}</div>
                                    <div class="small text-muted">
                                        {{ $score->lead?->program?->name ?? 'No program' }}
                                        @if($score->lead)
                                            | {{ $score->lead->email ?: $score->lead->phone ?: 'No contact' }}
                                        @endif
                                    </div>
                                </td>
                                <td class="fw-semibold">{{ $score->score }}/100</td>
                                <td><span class="badge text-bg-{{ $bandClass($score->band) }}">{{ str($score->band)->headline() }}</span></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(($score->explanation ?? []) as $signal => $points)
                                            <span class="badge text-bg-light border">{{ str($signal)->headline() }}: {{ $points }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $score->scored_at?->diffForHumans() ?: '-' }}</div>
                                    <div class="small text-muted">{{ $score->scorer?->name ?: 'System' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center py-3">No lead scores have been calculated yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
