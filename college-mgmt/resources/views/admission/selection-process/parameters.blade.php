@extends('layouts.admin')
@section('title', 'Scoring Parameters — ' . $step->name)
@section('page-title', 'Scoring Parameters')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admission.selection-process.steps', $program) }}" class="text-muted small"><i class="bi bi-arrow-left"></i> Back to Steps</a>
            <h4 class="fw-bold mb-0 mt-1">{{ $step->name }}</h4>
            <span class="text-muted small">{{ $program->name }} &middot; Max Score: {{ $step->max_score }} &middot; Weightage: {{ $step->weightage }}%</span>
        </div>
        <a href="{{ route('admission.selection-process.parameters.create', $step) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Parameter
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @php $totalMaxScore = $parameters->sum('max_score'); @endphp
    @if($parameters->isNotEmpty() && $totalMaxScore !== $step->max_score)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Parameter max scores sum to <strong>{{ $totalMaxScore }}</strong> but step max score is <strong>{{ $step->max_score }}</strong>.</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($parameters->isEmpty())
                <div class="text-center text-muted py-5">
                    <i class="bi bi-list-check fs-1 d-block mb-2"></i>
                    <p>No scoring parameters defined yet.</p>
                    <a href="{{ route('admission.selection-process.parameters.create', $step) }}" class="btn btn-primary btn-sm">Add First Parameter</a>
                </div>
            @else
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Parameter Name</th>
                        <th>Max Score</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($parameters as $param)
                    <tr>
                        <td class="fw-semibold">{{ $param->sort_order }}</td>
                        <td class="fw-semibold">{{ $param->name }}</td>
                        <td>{{ $param->max_score }}</td>
                        <td class="text-muted small">{{ $param->description ?? '—' }}</td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <a href="{{ route('admission.selection-process.parameters.edit', $param) }}" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('admission.selection-process.parameters.destroy', $param) }}" method="POST" onsubmit="return confirm('Delete this parameter?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button>
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
