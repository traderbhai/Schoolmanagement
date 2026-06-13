@extends('layouts.admin')

@section('title', 'Walk-ins')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3 class="fw-bold mb-1">Walk-in And Campus Visit Desk</h3><div class="text-muted small">Create walk-in leads, assign counsellors, and track visit conversion.</div></div>
</div>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Visitor</th><th>Program</th><th>Counsellor</th><th>Visit</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($walkIns as $walkIn)
                        <tr>
                            <td><div class="fw-semibold">{{ $walkIn->visitor_name }}</div><div class="small text-muted">{{ $walkIn->visitor_phone }}</div></td>
                            <td>{{ $walkIn->program->name ?? 'N/A' }}</td>
                            <td>{{ $walkIn->counsellor->name ?? 'Unassigned' }}</td>
                            <td>{{ optional($walkIn->visited_at)->format('d M Y H:i') }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($walkIn->status) }}</span></td>
                            <td>
                                @if(!$walkIn->lead_id)
                                <form method="POST" action="{{ route('admission.walk-ins.convert', $walkIn) }}">@csrf<button class="btn btn-sm btn-outline-success">Convert</button></form>
                                @else
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admission.leads.show', $walkIn->lead_id) }}">Lead</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-bold">Quick Walk-in</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admission.walk-ins.store') }}" class="vstack gap-2">
                    @csrf
                    <input class="form-control form-control-sm" name="visitor_name" placeholder="Visitor name" required>
                    <input class="form-control form-control-sm" name="visitor_phone" placeholder="Phone">
                    <input class="form-control form-control-sm" name="visitor_email" placeholder="Email">
                    <input class="form-control form-control-sm" name="guardian_name" placeholder="Guardian name">
                    <select class="form-select form-select-sm" name="program_id"><option value="">Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>
                    <select class="form-select form-select-sm" name="assigned_counsellor_id"><option value="">Counsellor</option>@foreach($counsellors as $counsellor)<option value="{{ $counsellor->id }}">{{ $counsellor->name }}</option>@endforeach</select>
                    <input class="form-control form-control-sm" name="purpose" value="admission_enquiry" required>
                    <input class="form-control form-control-sm" type="datetime-local" name="next_followup_at">
                    <textarea class="form-control form-control-sm" name="notes" rows="2" placeholder="Visit notes"></textarea>
                    <button class="btn btn-primary btn-sm">Record Walk-in</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-bold">Conversion Report</div>
            <div class="list-group list-group-flush">@foreach($report as $row)<div class="list-group-item d-flex justify-content-between"><span>{{ $row['counsellor'] }}</span><strong>{{ $row['conversion_pct'] }}%</strong></div>@endforeach</div>
        </div>
    </div>
</div>
@endsection
