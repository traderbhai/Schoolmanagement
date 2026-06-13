@extends('layouts.admin')
@section('title', 'Applicant Journeys')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3 mb-3">Applicant Journey Designer</h1>
    <div class="row g-4">
        <div class="col-lg-4"><form method="POST" action="{{ route('admission.journeys.store') }}" class="card">@csrf<div class="card-header fw-semibold">Publish Journey</div><div class="card-body vstack gap-3"><input class="form-control" name="name" placeholder="Journey name" required><select class="form-select" name="program_id"><option value="">All programs</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select><textarea class="form-control" name="stages_json" rows="3" required>["draft","submitted","under_review","selected","enrolled"]</textarea><textarea class="form-control" name="documents_json" rows="3">[]</textarea><textarea class="form-control" name="enrollment_blockers_json" rows="3">["registration_fee","verified_documents"]</textarea><textarea class="form-control" name="applicant_instructions" rows="3" placeholder="Applicant-visible instructions"></textarea><button class="btn btn-primary">Publish</button></div></form></div>
        <div class="col-lg-8"><div class="card"><div class="card-header fw-semibold">Published Journeys</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Program</th><th>Version</th><th>Active</th></tr></thead><tbody>@forelse($journeys as $journey)<tr><td>{{ $journey->name }}</td><td>{{ $journey->program_id ?: 'All' }}</td><td>{{ $journey->currentVersion?->version ?? '-' }}</td><td>{{ $journey->is_active ? 'Yes' : 'No' }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No journeys.</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</div>
@endsection
