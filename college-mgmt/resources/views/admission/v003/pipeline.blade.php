@extends('layouts.admin')
@section('title', 'Admission Pipeline')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Pipeline</h1><div class="btn-group"><a class="btn btn-outline-primary @if($objectType==='lead') active @endif" href="{{ route('admission.pipeline.index', ['object_type' => 'lead']) }}">Leads</a><a class="btn btn-outline-primary @if($objectType==='applicant') active @endif" href="{{ route('admission.pipeline.index', ['object_type' => 'applicant']) }}">Applicants</a></div></div>
    <div class="d-flex gap-3 overflow-auto pb-3">
        @foreach($snapshot['columns'] as $stage => $records)
            <div class="card flex-shrink-0" style="width: 280px;">
                <div class="card-header fw-semibold">{{ ucwords(str_replace('_', ' ', $stage)) }} <span class="badge bg-secondary">{{ $records->count() }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse($records->take(20) as $record)
                        <a class="list-group-item list-group-item-action" href="{{ $objectType === 'lead' ? route('admission.leads.show', $record) : route('admission.applicants.show', $record) }}"><strong>{{ $objectType === 'lead' ? $record->name : ($record->user?->name ?? $record->application_number) }}</strong><div class="small text-muted">{{ $record->program?->name }} - {{ $record->next_action ?? 'No next action' }}</div></a>
                    @empty
                        <div class="list-group-item text-muted">No records.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
