@extends('layouts.admin')
@section('title', 'Add Scoring Parameter')
@section('page-title', 'Add Scoring Parameter')

@section('content')
<div class="container-fluid py-4" style="max-width:600px">
    <a href="{{ route('admission.selection-process.parameters', $step) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Parameters</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">New Parameter — {{ $step->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.selection-process.parameters.store', $step) }}" method="POST">
                @csrf
                @include('admission.selection-process._parameter-form', ['parameter' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save Parameter</button>
                    <a href="{{ route('admission.selection-process.parameters', $step) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
