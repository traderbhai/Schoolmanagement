@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $title }}</h1>
            <div class="small text-muted">{{ $description }}</div>
        </div>
        @include('academics.pmc.v041.partials.nav')
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <form class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label small">Program</label><select class="form-select form-select-sm" name="program_id"><option value="">All programs</option>@foreach($selectorOptions['programs'] ?? [] as $program)<option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->code ?: $program->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small">Batch</label><select class="form-select form-select-sm" name="batch_id"><option value="">All batches</option>@foreach($selectorOptions['batches'] ?? [] as $batch)<option value="{{ $batch->id }}" @selected((string) request('batch_id') === (string) $batch->id)>{{ $batch->code ?: $batch->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small">Term</label><select class="form-select form-select-sm" name="term_id"><option value="">All terms</option>@foreach($selectorOptions['terms'] ?? [] as $term)<option value="{{ $term->id }}" @selected((string) request('term_id') === (string) $term->id)>{{ $term->name }}</option>@endforeach</select></div>
                <div class="col-md-3 d-flex gap-1"><button class="btn btn-sm btn-primary">Check Readiness</button><a href="{{ route('academics.pmc.timetable-launch.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="fw-semibold">Launch Status</div>
                <div class="small text-muted">Generation is blocked only when hard prerequisites fail. Warnings remain visible and auditable.</div>
            </div>
            <div class="text-end">
                <span class="badge text-bg-{{ $readiness['status'] === 'ready' ? 'success' : ($readiness['status'] === 'warning' ? 'warning' : 'danger') }}">{{ $readiness['status'] }}</span>
                <div class="small text-muted">{{ $readiness['ready_count'] }} ready | {{ $readiness['warning_count'] }} warnings | {{ $readiness['blocked_count'] }} blocked</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Prerequisite</th><th>Status</th><th>Ready</th><th>Warnings</th><th>Blockers</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($readiness['checks'] as $check)
                        <tr>
                            <td><div class="fw-semibold">{{ $check['label'] }}</div><div class="small text-muted">{{ $check['message'] }}</div></td>
                            <td><span class="badge text-bg-{{ $check['status'] === 'ready' ? 'success' : ($check['status'] === 'warning' ? 'warning' : 'danger') }}">{{ $check['status'] }}</span></td>
                            <td>{{ $check['ready'] }}</td>
                            <td>{{ $check['warnings'] }}</td>
                            <td>{{ $check['blockers'] }}</td>
                            <td><a class="btn btn-sm btn-outline-primary" href="{{ route($check['route'], array_filter($check['filters'] ?? [])) }}">Open</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer py-2 d-flex flex-wrap gap-2 justify-content-between">
            <span class="small text-muted">This same readiness result is used by the generation gate.</span>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('academics.pmc.timetable-generator.index', array_filter($readiness['scope'] ?? [])) }}">Go to generator</a>
        </div>
    </div>
</div>
@endsection
