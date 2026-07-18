@extends('layouts.admin')
@section('title', 'Admission Workflow Config')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h4 mb-1">Workflow Config</h1>
    <div class="text-muted mb-4">Configure lead stages, outcomes, reasons, SLA profiles, tags, and attention rules.</div>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.workflow-config.store') }}" class="card mb-4">
                @csrf
                <div class="card-header fw-semibold">Config Item</div>
                <div class="card-body vstack gap-3">
                    <select aria-label="Type" name="type" class="form-select">
                        @foreach(['lead_stage','outcome','reason','sla_profile','attention_rule'] as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    <input aria-label="Label" name="label" class="form-control" placeholder="Label" required>
                    <input aria-label="Workflow configuration key" name="key" class="form-control" placeholder="Key (optional)">
                    <input aria-label="Sort Order" name="sort_order" type="number" class="form-control" value="100">
                    <button class="btn btn-primary">Save Config</button>
                </div>
            </form>
            <form method="POST" action="{{ route('admission.workflow-config.tags.store') }}" class="card">
                @csrf
                <div class="card-header fw-semibold">Tag</div>
                <div class="card-body vstack gap-3">
                    <input aria-label="Tag name" name="name" class="form-control" placeholder="Tag name" required>
                    <input aria-label="Bootstrap color" name="color" class="form-control" placeholder="Bootstrap color" value="secondary">
                    <button class="btn btn-outline-primary">Save Tag</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            @foreach($configs as $type => $rows)
                <div class="card mb-3">
                    <div class="card-header fw-semibold">{{ ucwords(str_replace('_', ' ', $type)) }}</div>
                    <div class="list-group list-group-flush">
                        @foreach($rows as $row)
                            <div class="list-group-item d-flex justify-content-between"><span>{{ $row->label }}</span><span class="text-muted">{{ $row->key }}</span></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="card">
                <div class="card-header fw-semibold">Tags</div>
                <div class="card-body d-flex flex-wrap gap-2">
                    @forelse($tags as $tag)<span class="badge bg-{{ $tag->color }}">{{ $tag->name }}</span>@empty<span class="text-muted">No tags.</span>@endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
