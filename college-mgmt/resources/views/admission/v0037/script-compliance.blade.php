@extends('layouts.admin')

@section('title', 'Script Compliance')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Script Compliance</h3>
            <div class="text-muted small">Check whether counsellors and telecallers covered the required call/playbook points before coaching or escalation.</div>
        </div>
        <a href="{{ route('admission.counsellor-performance.index') }}" class="btn btn-outline-primary btn-sm">Open Performance</a>
    </div>

    <div class="alert alert-info py-2 small mb-3">
        <strong>Review workflow:</strong> confirm the active script template, compare completion logs, then coach users with missed steps before changing targets or escalation rules.
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Templates</span>
                    <span class="small text-muted">{{ $templates->count() }} active</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($templates as $template)
                        <div class="list-group-item">
                            <strong>{{ $template->name }}</strong>
                            <div class="small text-muted">{{ count($template->steps ?? []) }} steps - {{ ucwords(str_replace('_', ' ', $template->stage ?: 'stage not set')) }}</div>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">
                            <div class="fw-semibold text-dark">No call scripts are configured</div>
                            <div class="small">Create script templates before measuring call-quality coverage or daily script compliance.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Completion Logs</span>
                    <span class="small text-muted">{{ $logs->total() }} records</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" aria-label="Script compliance logs">
                        <thead class="table-light">
                            <tr>
                                <th>Script</th>
                                <th>Subject</th>
                                <th>Compliance</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td>{{ $log->template?->name ?? 'Script template missing' }}</td>
                                    <td>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                                    <td>{{ $log->compliance_percent }}%</td>
                                    <td>{{ $log->created_at?->diffForHumans() ?? 'Time not captured' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No script completion logs are available yet</div>
                                        <div class="small">Logs appear after staff save call outcomes from the Calling Desk with script steps marked covered, missed, or not applicable.</div>
                                        <a href="{{ route('admission.calling-desk.index') }}" class="btn btn-sm btn-outline-primary mt-2">Open Calling Desk</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
