@extends('layouts.admin')
@section('title', $config['title'])
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h1 class="h4 mb-1">{{ $config['title'] }}</h1><div class="small text-muted">{{ $config['description'] }}</div></div>@include('academics.dean-os.partials.nav')</div>
    <div class="row g-2 mb-3">
        @foreach(['Open'=>$data['open'],'Critical'=>$data['critical'],'Overdue'=>$data['overdue'],'Avg Score'=>$data['avg_score']] as $label=>$value)
            <div class="col-6 col-lg-3"><a class="card shadow-sm text-decoration-none" href="{{ request()->fullUrlWithQuery(['status'=>'open']) }}"><div class="card-body py-2"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0">{{ $value }}</div></div></a></div>
        @endforeach
    </div>
    <div class="card shadow-sm"><div class="card-header py-2 d-flex justify-content-between"><span class="small text-muted">Visible filter summary: {{ str_replace('_',' ', $config['record_type']) }} | search/filter/sort/pagination enabled by source query</span><a href="{{ route('academics.dean-os.export', $config['record_type']) }}" class="btn btn-sm btn-outline-secondary">Export Current View</a></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Record</th><th>Program</th><th>Owner</th><th>Severity</th><th>Status</th><th>Due</th><th>Score</th></tr></thead><tbody>
        @foreach($data['records'] as $record)<tr><td><div class="fw-semibold">{{ $record->title }}</div><div class="small text-muted">{{ $record->source_type }} {{ $record->source_key }}</div></td><td>{{ $record->program?->code ?? '-' }}</td><td>{{ $record->owner?->name ?? 'Unassigned' }}</td><td>{{ $record->severity }}</td><td>{{ $record->status }}</td><td>{{ optional($record->due_at)->format('d M Y') }}</td><td>{{ $record->score }}</td></tr>@endforeach
    </tbody></table></div><div class="card-footer py-2">{{ $data['records']->links() }}</div></div>
</div>
@endsection
