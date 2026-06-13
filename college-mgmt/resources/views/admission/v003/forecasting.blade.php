@extends('layouts.admin')
@section('title', 'Admission Forecasting')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Forecasting</h1><form method="POST" action="{{ route('admission.forecasting.snapshot') }}">@csrf<button class="btn btn-primary">Generate Snapshot</button></form></div>
    @if($snapshot)
        <div class="row g-3 mb-4">@foreach(['Target Seats'=>$snapshot->target_seats,'Leads'=>$snapshot->lead_count,'Applications'=>$snapshot->application_count,'Projected Enrollments'=>$snapshot->projected_enrollments,'Projected Gap'=>$snapshot->projected_gap] as $label => $value)<div class="col-6 col-lg"><div class="card"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="h3 mb-0">{{ $value }}</div></div></div></div>@endforeach</div>
    @endif
    <div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Created</th><th>Program</th><th>Source</th><th>Rate</th><th>Gap</th></tr></thead><tbody>@forelse($snapshots as $row)<tr><td>{{ $row->created_at?->format('d M H:i') }}</td><td>{{ $row->program_id ?: 'All' }}</td><td>{{ $row->source ?: 'All' }}</td><td>{{ $row->expected_conversion_rate }}%</td><td>{{ $row->projected_gap }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No forecast snapshots.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
