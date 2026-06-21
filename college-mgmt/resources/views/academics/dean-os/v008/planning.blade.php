@extends('layouts.admin')
@section('title', 'Dean Academic Planning')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Academic Planning Cycle OS</h1><div class="small text-muted">Annual plans, semester readiness, calendar approval, curriculum rollout, and teaching-load approval.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="alert alert-info border-0 shadow-sm small mb-3">
        <div class="fw-semibold mb-1">Academic planning sequence</div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-light border">1. Create annual or semester plan</span>
            <span class="badge text-bg-light border">2. Check readiness blockers</span>
            <span class="badge text-bg-light border">3. Create action for blocker</span>
            <span class="badge text-bg-light border">4. Approve only when evidence is ready</span>
            <span class="badge text-bg-light border">5. Publish or revise calendar</span>
        </div>
        <div class="text-muted mt-2">Planning records coordinate PMC, CoE, IQAC, Program Leadership, and Course Delivery. Readiness blockers should become owned Dean action items before approval.</div>
    </div>
    <div class="row g-2 mb-3">
        @foreach(['Active Plans'=>$kpis['active_plans'],'Published Calendars'=>$kpis['published_calendars'],'Readiness Blockers'=>$kpis['readiness_blockers'],'Load Approvals'=>$kpis['load_approvals']] as $label=>$value)
            <div class="col-md-3"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('academics.dean-os.planning.store') }}" class="card shadow-sm">@csrf
                <div class="card-header py-2"><div class="fw-semibold">Create Plan</div><div class="small text-muted">Choose the planning cycle that matches the decision you want the Dean to govern.</div></div>
                <div class="card-body vstack gap-2">
                    <input class="form-control form-control-sm" name="title" placeholder="Plan title" required>
                    <select class="form-select form-select-sm" name="cycle_type"><option value="annual_plan">Annual Plan</option><option value="semester_readiness">Semester Readiness</option><option value="academic_calendar">Academic Calendar</option><option value="curriculum_rollout">Curriculum Rollout</option><option value="teaching_load">Teaching Load</option></select>
                    <input class="form-control form-control-sm" name="academic_year" placeholder="Academic year">
                    <select class="form-select form-select-sm" name="status"><option value="draft">Draft</option><option value="branch_review">Branch Review</option><option value="dean_review">Dean Review</option><option value="approved">Approved</option><option value="published">Published</option><option value="revised">Revised</option></select>
                    <button class="btn btn-sm btn-primary" onclick="return confirm('Create this Dean academic plan?')">Create</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm"><div class="card-header py-2"><div class="fw-semibold">Planning Cycles</div><div class="small text-muted">Approval should follow readiness, not replace it.</div></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Plan</th><th>Type</th><th>Status</th><th>Readiness</th><th>Action</th></tr></thead><tbody>
            @foreach($cycles as $cycle)<tr><td>{{ $cycle->title }}</td><td>{{ str_replace('_',' ', $cycle->cycle_type) }}</td><td><span class="badge text-bg-secondary">{{ $cycle->status }}</span></td><td>{{ $cycle->readiness_score }}%</td><td><form method="POST" action="{{ route('academics.dean-os.planning.approve', $cycle) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this Dean planning cycle?')">Approve</button></form></td></tr>@endforeach
            </tbody></table></div><div class="card-footer py-2">{{ $cycles->links() }}</div></div>
        </div>
    </div>
    <div class="card shadow-sm mt-3"><div class="card-header py-2"><div class="fw-semibold">Readiness Blockers</div><div class="small text-muted">Every blocker needs an owner, due date, and action item before the plan is considered operationally ready.</div></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Section</th><th>Blocker</th><th>Owner</th><th>Due</th><th></th></tr></thead><tbody>
        @foreach($blockers as $item)<tr><td>{{ str_replace('_',' ', $item->section) }}</td><td>{{ $item->title }}</td><td>{{ $item->owner?->name ?? 'Unassigned' }}</td><td>{{ optional($item->due_at)->format('d M Y') }}</td><td><form method="POST" action="{{ route('academics.dean-os.semester-readiness.action', $item) }}">@csrf<button class="btn btn-sm btn-outline-primary" onclick="return confirm('Create or reuse the action item for this readiness blocker?')">Create Action</button></form></td></tr>@endforeach
    </tbody></table></div><div class="card-footer py-2">{{ $blockers->links() }}</div></div>
</div>
@endsection
