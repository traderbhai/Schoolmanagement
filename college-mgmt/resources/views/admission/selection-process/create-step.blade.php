@extends('layouts.admin')
@section('title', 'Add Selection Step')
@section('page-title', 'Add Selection Step')

@section('content')
<div class="container-fluid py-4" style="max-width:650px">
    <a href="{{ route('admission.selection-process.steps', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Steps</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">New Selection Step — {{ $program->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.selection-process.steps.store', $program) }}" method="POST">
                @csrf
                @include('admission.selection-process._step-form', ['step' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save Step</button>
                    <a href="{{ route('admission.selection-process.steps', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
