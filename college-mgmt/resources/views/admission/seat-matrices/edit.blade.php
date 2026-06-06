@extends('layouts.admin')
@section('title', 'Edit Seat Matrix')
@section('page-title', 'Edit Seat Matrix')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">
    <a href="{{ route('admission.seat-matrices.index', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Seat Matrices</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Edit Seat Matrix — {{ $program->name }} / {{ $seatMatrix->batch?->name ?? 'All Batches' }}</div>
        <div class="card-body">
            <form action="{{ route('admission.seat-matrices.update', $seatMatrix) }}" method="POST">
                @csrf @method('PUT')
                @include('admission.seat-matrices._form', ['matrix' => $seatMatrix])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Update Matrix</button>
                    <a href="{{ route('admission.seat-matrices.index', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
