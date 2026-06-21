@extends('layouts.admin')
@section('title', 'Admission Pipeline')
@section('content')
@php
    $objectLabel = $objectType === 'lead' ? 'leads' : 'applicants';
    $objectSingular = $objectType === 'lead' ? 'lead' : 'applicant';
@endphp
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Admission Pipeline</h1>
            <p class="text-muted mb-0">
                Review visible {{ $objectLabel }} by stage, open the source record for follow-up, and move stages only after the required contact, document, payment, assessment, or offer action is complete.
            </p>
        </div>
        <div class="btn-group">
            <a class="btn btn-outline-primary @if($objectType==='lead') active @endif" href="{{ route('admission.pipeline.index', ['object_type' => 'lead']) }}">Leads</a>
            <a class="btn btn-outline-primary @if($objectType==='applicant') active @endif" href="{{ route('admission.pipeline.index', ['object_type' => 'applicant']) }}">Applicants</a>
        </div>
    </div>
    <div class="alert alert-light border small d-flex flex-wrap justify-content-between gap-2">
        <span><strong>Scope:</strong> {{ ucfirst($objectLabel) }} visible to your Admission role and hierarchy.</span>
        <span><strong>Board:</strong> {{ $snapshot['board']->name }}; showing up to 20 records per stage.</span>
    </div>
    <div class="d-flex gap-3 overflow-auto pb-3">
        @foreach($snapshot['columns'] as $stage => $records)
            <div class="card flex-shrink-0" style="width: 280px;">
                <div class="card-header fw-semibold">{{ ucwords(str_replace('_', ' ', $stage)) }} <span class="badge bg-secondary">{{ $records->count() }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse($records->take(20) as $record)
                        <a class="list-group-item list-group-item-action" href="{{ $objectType === 'lead' ? route('admission.leads.show', $record) : route('admission.applicants.show', $record) }}"><strong>{{ $objectType === 'lead' ? $record->name : ($record->user?->name ?? $record->application_number) }}</strong><div class="small text-muted">{{ $record->program?->name }} - {{ $record->next_action ?? 'Next action not set' }}</div></a>
                    @empty
                        <div class="list-group-item text-muted small">
                            <div class="fw-semibold text-body">No {{ $objectLabel }} in this stage</div>
                            <div>
                                {{ ucfirst($objectLabel) }} appear here after staff update the {{ $objectSingular }} status to
                                <span class="fw-semibold">{{ ucwords(str_replace('_', ' ', $stage)) }}</span>.
                                Check the other stages or open the {{ $objectType === 'lead' ? 'lead list' : 'applicant list' }} for the full source view.
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
