@extends('layouts.admin')
@section('title', 'Edit Selection Step')
@section('page-title', 'Edit Selection Step')

@section('content')
<div class="container-fluid py-4" style="max-width:650px">
    <a href="{{ route('admission.selection-process.steps', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Steps</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Edit Step — {{ $step->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.selection-process.steps.update', $step) }}" method="POST">
                @csrf @method('PUT')
                @include('admission.selection-process._step-form', ['step' => $step])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Update Step</button>
                    <a href="{{ route('admission.selection-process.steps', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
