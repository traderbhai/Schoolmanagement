@extends('layouts.admin')
@section('title', 'Selection Process — ' . $program->name)
@section('page-title', 'Selection Process Configuration')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">{{ $program->name }}</h4>
            <span class="text-muted small">Selection Steps & Scoring Configuration</span>
        </div>
        <a href="{{ route('admission.selection-process.create-step', $program) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Step
        </a>
    </div>

    {{-- Program Switcher --}}
    <div class="mb-3">
        <select class="form-select form-select-sm" style="width:auto" onchange="window.location='/admission/selection-process/'+this.value+'/steps'">
            @foreach($programs as $p)
                <option value="{{ $p->id }}" {{ $p->id == $program->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @php $totalWeight = $steps->sum('weightage'); @endphp
    @if($steps->isNotEmpty() && abs($totalWeight - 100) > 0.01)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Total weightage is <strong>{{ $totalWeight }}%</strong>. Steps should ideally sum to 100%.</div>
    @endif

    @if($steps->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>
                <p>No selection steps configured yet.</p>
                <a href="{{ route('admission.selection-process.create-step', $program) }}" class="btn btn-primary btn-sm">Add First Step</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach($steps as $step)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary-subtle text-primary me-1">Step {{ $step->step_order }}</span>
                                <span class="badge bg-secondary-subtle text-secondary">{{ $step->type_label }}</span>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admission.selection-process.edit-step', $step) }}" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('admission.selection-process.destroy-step', $step) }}" method="POST" onsubmit="return confirm('Delete this step?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <h6 class="fw-bold mb-1">{{ $step->name }}</h6>
                        <div class="row g-2 text-center mt-2">
                            <div class="col-4">
                                <div class="small text-muted">Max Score</div>
                                <div class="fw-semibold">{{ $step->max_score }}</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Weightage</div>
                                <div class="fw-semibold">{{ $step->weightage }}%</div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Parameters</div>
                                <div class="fw-semibold">{{ $step->scoring_parameters_count }}</div>
                            </div>
                        </div>
                        @if($step->instructions)
                            <p class="text-muted small mt-2 mb-0">{{ Str::limit($step->instructions, 80) }}</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="{{ route('admission.selection-process.parameters', $step) }}" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-list-check me-1"></i>Manage Scoring Parameters
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
