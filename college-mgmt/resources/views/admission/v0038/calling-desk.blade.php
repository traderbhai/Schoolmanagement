@extends('layouts.admin')
@section('title', 'Admission Calling Desk')
@section('content')
<div class="container-fluid py-3">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3 class="fw-bold mb-1">Admission Calling Desk</h3><div class="text-muted small">One-screen speed mode for telecallers and counsellors.</div></div>
    <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('admission.counsellor-desk.index') }}">Counsellor Desk</a><a class="btn btn-sm btn-outline-success" href="{{ route('admission.objection-analytics.index') }}">Objections</a></div>
</div>
<div class="row g-2 mb-3">
@foreach(['attempts_today' => $attempts_today, 'contact_rate' => $contact_rate . '%', 'callback_due' => $callback_due, 'parent_due' => $parent_due] as $label => $value)
    <div class="col-6 col-lg-3"><a class="text-decoration-none text-dark" href="{{ route('admission.calling-desk.index') }}"><div class="card border-0 shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="fs-4 fw-bold">{{ $value }}</div></div></div></a></div>
@endforeach
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">Active Call</div>
            <div class="card-body">
                @if($active)
                    @php($isLead = $active instanceof \App\Models\Lead)
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <h5 class="mb-1">{{ $isLead ? $active->name : ($active->user?->name ?? $active->application_number) }}</h5>
                            <div class="small text-muted">{{ $isLead ? $active->phone : ($active->personal_data['phone'] ?? '') }} | {{ $active->program?->name }} | {{ ucfirst($active->priority ?? 'normal') }}</div>
                            <div class="mt-2">{{ $active->next_action ?: 'Call and confirm next step.' }}</div>
                        </div>
                        <div class="text-end"><span class="{{ $active->status_badge ?? 'badge bg-secondary' }}">{{ $active->status_label ?? ucfirst($active->status) }}</span></div>
                    </div>
                    <form method="POST" action="{{ route('admission.calling-desk.outcome') }}" class="row g-2 mt-3">
                        @csrf
                        <input type="hidden" name="subject_type" value="{{ $isLead ? 'lead' : 'applicant' }}">
                        <input type="hidden" name="subject_id" value="{{ $active->id }}">
                        <input type="hidden" name="script_template_id" value="{{ $script?->id }}">
                        <div class="col-md-3"><label class="form-label small">Disposition</label><select name="disposition" class="form-select form-select-sm"><option>connected</option><option>not_reachable</option><option>busy</option><option>wrong_number</option><option>no_answer</option></select></div>
                        <div class="col-md-3"><label class="form-label small">Outcome</label><select name="outcome" class="form-select form-select-sm"><option value="interested">Interested</option><option value="callback">Callback</option><option value="not_interested">Not Interested</option><option value="parent_pending">Parent Pending</option><option value="escalated">Escalated</option></select></div>
                        <div class="col-md-3"><label class="form-label small">Retry Due</label><input type="datetime-local" name="retry_due_at" class="form-control form-control-sm"></div>
                        <div class="col-md-3"><label class="form-label small">Duration Seconds</label><input type="number" name="duration_seconds" value="180" class="form-control form-control-sm"></div>
                        @if($script)
                            <div class="col-12"><div class="small fw-bold mb-1">{{ $script->name }}</div><div class="row g-1">@foreach($script->steps ?? [] as $idx => $step)<div class="col-md-6 col-xl-4"><label class="form-label small mb-0">{{ $step }}</label><select name="script_results[]" class="form-select form-select-sm"><option value="covered">Covered</option><option value="missed">Missed</option><option value="na">Not applicable</option></select></div>@endforeach</div></div>
                        @endif
                        <div class="col-md-8"><label class="form-label small">Notes</label><input name="notes" class="form-control form-control-sm" value="Discussed program fit, parent decision, and next action."></div>
                        <div class="col-md-4"><label class="form-label small">Next Action</label><input name="next_action" class="form-control form-control-sm" value="Send checklist and schedule follow-up"></div>
                        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-sm btn-primary">Save Call Outcome</button></form><form method="POST" action="{{ route('admission.call-attempts.skip') }}">@csrf<input type="hidden" name="subject_type" value="{{ $isLead ? 'lead' : 'applicant' }}"><input type="hidden" name="subject_id" value="{{ $active->id }}"><input type="hidden" name="reason" value="Temporarily skipped during calling desk session"><button class="btn btn-sm btn-outline-secondary">Skip</button></form></div>
                @else
                    <div class="text-muted">No eligible calling records found for your current scope.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-bold">Next Call Queue</div><div class="table-responsive"><table class="table table-sm mb-0" aria-label="Next call queue"><thead><tr><th>Candidate</th><th>Type</th><th>Score</th><th>Action</th></tr></thead><tbody>@foreach($queue->take(12) as $item)<tr><td>{{ $item->record instanceof \App\Models\Lead ? $item->record->name : ($item->record->user?->name ?? $item->record->application_number) }}</td><td>{{ $item->type }}</td><td>{{ $item->queue_score }}</td><td class="small">{{ $item->recommended_action }}</td></tr>@endforeach</tbody></table></div></div>
        <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-bold">Recent Objections</div><div class="table-responsive"><table class="table table-sm mb-0" aria-label="Recent objections"><thead><tr><th>Subject</th><th>Stage</th><th>Status</th></tr></thead><tbody>@forelse($objections as $objection)<tr><td>{{ class_basename($objection->subject_type) }} #{{ $objection->subject_id }}</td><td>{{ $objection->stage }}</td><td>{{ $objection->status }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center">No objection trends.</td></tr>@endforelse</tbody></table></div></div>
    </div>
</div>
</div>
@endsection
