@extends('layouts.admin')
@section('title', 'Add Fee Installment')
@section('page-title', 'Add Fee Installment')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">
    <a href="{{ route('admission.fee-installments.index', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Installments</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">New Installment — {{ $program->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.fee-installments.store', $program) }}" method="POST">
                @csrf
                @include('admission.fee-installments._form', ['installment' => null])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Save Installment</button>
                    <a href="{{ route('admission.fee-installments.index', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
