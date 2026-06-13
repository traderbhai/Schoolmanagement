@extends('layouts.admin')
@section('title', 'Admission Process Templates')
@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admission Process Templates</h1>
            <div class="text-muted">Configure per-program intake stages, offer validity, waitlist rules, and SLA-ready process steps.</div>
        </div>
        <a href="{{ route('admission.workbench') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Workbench</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <form method="POST" action="{{ route('admission.process-templates.store') }}" class="card">
                @csrf
                <div class="card-header fw-semibold">New Template</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Program</label>
                        <select name="program_id" class="form-select" required>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}">{{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Template Name</label>
                        <input name="name" class="form-control" required placeholder="MBA 2026 Admission Process">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Offer Validity Days</label>
                        <input name="offer_validity_days" type="number" min="1" class="form-control" value="15">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Waitlist Rule</label>
                        <input name="waitlist_rule" class="form-control" placeholder="Auto-promote by merit rank after expiry">
                    </div>
                    <button class="btn btn-primary w-100">Create Template</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            @foreach($templates as $template)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $template->name }}</div>
                            <div class="small text-muted">{{ $template->program?->name }} {{ $template->batch ? '- ' . $template->batch->name : '' }}</div>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach($template->stages as $stage)
                                <span class="badge bg-light text-dark border">{{ $stage->sequence }}. {{ $stage->name }}</span>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('admission.process-templates.stages.store', $template) }}" class="row g-2">
                            @csrf
                            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="Stage name" required></div>
                            <div class="col-md-3"><input name="stage_key" class="form-control form-control-sm" placeholder="stage_key" required></div>
                            <div class="col-md-2"><input name="sequence" type="number" min="1" class="form-control form-control-sm" placeholder="Order" required></div>
                            <div class="col-md-2"><input name="sla_hours" type="number" min="1" class="form-control form-control-sm" placeholder="SLA hrs"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Save Stage</button></div>
                        </form>
                    </div>
                </div>
            @endforeach
            {{ $templates->links() }}
        </div>
    </div>
</div>
@endsection
