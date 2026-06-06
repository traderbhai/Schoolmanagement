@extends('layouts.admin')
@section('title', 'Configure Seat Matrix')
@section('page-title', 'Configure Seat Matrix')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">
    <a href="{{ route('admission.seat-matrices.index', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Seat Matrices</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">New Seat Matrix — {{ $program->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.seat-matrices.store', $program) }}" method="POST">
                @csrf
                @include('admission.seat-matrices._form', ['matrix' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save Matrix</button>
                    <a href="{{ route('admission.seat-matrices.index', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
