@extends('layouts.admin')
@section('title', 'Dean Analytics And Report Packs')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">Dean Analytics, Reports, And Saved Views</h1><div class="small text-muted">Trend charts, drill-down comparisons, scheduled reports, management review packs, and export contracts.</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-3">
        <div class="col-lg-5"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Trend Panels</div><div class="list-group list-group-flush">@foreach($charts as $chart)<div class="list-group-item d-flex justify-content-between"><span>{{ $chart }}</span><span class="badge text-bg-primary">DB-backed</span></div>@endforeach</div></div></div>
        <div class="col-lg-7"><div class="card shadow-sm"><div class="card-header py-2 fw-semibold">Scheduled Report Packs</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Pack</th><th>Schedule</th><th>Status</th><th>Generate</th></tr></thead><tbody>@foreach($packs as $pack)<tr><td>{{ $pack->name }}</td><td>{{ $pack->schedule }}</td><td>{{ $pack->status }}</td><td><form method="POST" action="{{ route('academics.dean-os.scheduled-reports.generate', $pack) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">Generate</button></form></td></tr>@endforeach</tbody></table></div><div class="card-footer py-2">{{ $packs->links() }}</div></div></div>
    </div>
    <div class="card shadow-sm mt-3"><div class="card-header py-2 fw-semibold">Summary By Operating Record</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Type</th><th>Status</th><th>Total</th></tr></thead><tbody>@foreach($recordSummary as $row)<tr><td>{{ str_replace('_',' ', $row->record_type) }}</td><td>{{ $row->status }}</td><td>{{ $row->total }}</td></tr>@endforeach</tbody></table></div></div>
</div>
@endsection
