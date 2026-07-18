@extends('layouts.admin')
@section('title', 'Scoring Parameters - ' . $step->name)
@section('page-title', 'Scoring Parameters')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admission.selection-process.steps', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Steps</a>
            <h4 class="fw-bold mb-0 mt-1">{{ $step->name }}</h4>
            <span class="text-muted small">{{ $program->name }} - Max Score: {{ $step->max_score }} - Weightage: {{ $step->weightage }}%</span>
        </div>
        <a href="{{ route('admission.selection-process.parameters.create', $step) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Parameter
        </a>
    </div>

    <div class="alert alert-info border-0 shadow-sm mb-3">
        <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i>Scoring parameter setup</div>
        <div class="small text-muted">Parameters define the evaluator rubric used for score entry, scorecards, merit-list composite score, and selection decisions. Parameter max scores should add up to the step max score before sessions are conducted.</div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @php $totalMaxScore = $parameters->sum('max_score'); @endphp
    @if($parameters->isNotEmpty() && $totalMaxScore !== $step->max_score)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Parameter max scores sum to <strong>{{ $totalMaxScore }}</strong> but step max score is <strong>{{ $step->max_score }}</strong>. Fix the rubric before scoring starts.</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($parameters->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-list-check fs-1 d-block mb-2 text-muted"></i>
                    <div class="fw-semibold text-dark mb-1">No scoring parameters are defined for this step yet</div>
                    <p class="text-muted small mb-3">Add rubric items such as communication, subject knowledge, analytical ability, confidence, or writing quality before evaluators enter scores.</p>
                    <a href="{{ route('admission.selection-process.parameters.create', $step) }}" class="btn btn-primary btn-sm">Add First Parameter</a>
                </div>
            @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Parameter Name</th>
                        <th scope="col">Max Score</th>
                        <th scope="col">Description</th>
                        <th aria-label="Actions" scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($parameters as $param)
                    <tr>
                        <td class="fw-semibold">{{ $param->sort_order }}</td>
                        <td class="fw-semibold">{{ $param->name }}</td>
                        <td>{{ $param->max_score }}</td>
                        <td class="text-muted small">{{ $param->description ?? 'Description not provided' }}</td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('admission.selection-process.parameters.edit', $param) }}" class="btn btn-sm btn-outline-secondary py-0 px-1" aria-label="Edit scoring parameter"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('admission.selection-process.parameters.destroy', $param) }}" method="POST" onsubmit="return confirm('Delete scoring parameter {{ addslashes($param->name) }}? Confirm assessment rubrics, evaluator scoring, merit calculations, and historical score reports no longer depend on it.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" aria-label="Delete scoring parameter {{ $param->name }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="2" class="text-end fw-semibold">Total</td>
                        <td class="fw-bold">{{ $totalMaxScore }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            @endif
        </div>
    </div>
</div>
@endsection
