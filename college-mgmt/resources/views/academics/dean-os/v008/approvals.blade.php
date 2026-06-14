@extends('layouts.admin')
@section('title', 'Dean Approval Cockpit')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Unified Dean Approval Cockpit</h1><div class="small text-muted">Curriculum, calendar, readiness, teaching load, timetable, exam, IQAC, student, and handoff approvals.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-2 mb-3">@foreach(['Pending'=>$pending,'Overdue'=>$overdue,'High Risk'=>$high_risk] as $label=>$value)<div class="col-md-4"><div class="card shadow-sm"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></div></div>@endforeach</div>
    <div class="card shadow-sm"><div class="card-header py-2 small text-muted">Visible filter summary: all approval streams | sort: latest | export from Dean Reports</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Approval</th><th>Type</th><th>Owner</th><th>Risk</th><th>Status</th><th>Decision</th></tr></thead><tbody>
        @foreach($items as $item)<tr><td>{{ $item->title }}</td><td>{{ str_replace('_',' ', $item->approval_type) }}</td><td>{{ $item->owner?->name ?? 'Unassigned' }}</td><td>{{ $item->risk_level }}</td><td>{{ $item->status }}</td><td><form method="POST" action="{{ route('academics.dean-os.approval-cockpit.decide', $item) }}" class="d-flex gap-1">@csrf @method('PATCH')<select class="form-select form-select-sm" name="status"><option value="approved">Approve</option><option value="returned">Return</option><option value="rejected">Reject</option></select><input class="form-control form-control-sm" name="decision_reason" placeholder="Reason"><button class="btn btn-sm btn-primary">Save</button></form></td></tr>@endforeach
    </tbody></table></div><div class="card-footer py-2">{{ $items->links() }}</div></div>
</div>
@endsection
