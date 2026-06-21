@extends('layouts.admin')
@section('title', 'Admission Automations')
@section('content')
<div class="container-fluid py-4">
    <x-ui.page-header
        title="Admission Automations"
        subtitle="Create deterministic rules that assign work, send reminders, update priority, or create attention items without bypassing safety controls."
    />
    @unless($canManageAutomations)
        <div class="alert alert-warning py-2">Read-only view for your Admission scope. Automation rule changes and manual runs require Admission leadership approval.</div>
    @endunless
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Automation operating sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Define trigger</span>
            <span class="badge text-bg-light border">2. Add conditions</span>
            <span class="badge text-bg-light border">3. Preview impact</span>
            <span class="badge text-bg-light border">4. Activate rule</span>
            <span class="badge text-bg-light border">5. Review execution log</span>
        </div>
        <div class="text-muted mt-2">Rules should be idempotent and scoped. Send-message actions still depend on approved templates, consent, quiet hours, and provider availability.</div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4"><form method="POST" action="{{ route('admission.automations.store') }}" class="card" onsubmit="return confirm('Save this Admission automation rule?')">@csrf<div class="card-header"><div class="fw-semibold">Rule Builder</div><div class="small text-muted">Start with narrow conditions, then use the execution log to confirm the rule touched the expected records only.</div></div><div class="card-body vstack gap-3"><input class="form-control" name="name" placeholder="Rule name" required><input class="form-control" name="trigger" placeholder="lead_created" required><input class="form-control" name="priority" type="number" value="100"><textarea class="form-control" name="conditions_json" rows="3" placeholder='{"source":"web_form"}'></textarea><textarea class="form-control" name="actions_json" rows="5" required>[{"type":"score_lead"},{"type":"next_action","value":"Call within SLA"}]</textarea><button class="btn btn-primary" @disabled(! $canManageAutomations)>Save Automation</button></div></form></div>
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-header"><div class="fw-semibold">Rules</div><div class="small text-muted">Priority controls rule order; inactive rules are retained for audit but should not affect live work.</div></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Trigger</th><th>Priority</th><th>Active</th></tr></thead><tbody>@forelse($automations as $automation)<tr><td>{{ $automation->name }}</td><td>{{ $automation->trigger }}</td><td>{{ $automation->priority }}</td><td>{{ $automation->is_active ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No automations.</td></tr>@endforelse</tbody></table></div></div>
            <div class="card"><div class="card-header"><div class="fw-semibold">Recent Executions</div><div class="small text-muted">Check this after a run to confirm status, timing, and duplicate suppression.</div></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Rule</th><th>Status</th><th>When</th></tr></thead><tbody>@forelse($executions as $execution)<tr><td>{{ $execution->automation?->name }}</td><td>{{ $execution->status }}</td><td>{{ $execution->created_at?->diffForHumans() }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3">No executions.</td></tr>@endforelse</tbody></table></div></div>
        </div>
    </div>
</div>
@endsection
