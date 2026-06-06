@extends('layouts.admin')
@section('title', 'Edit Fee Installment')
@section('page-title', 'Edit Fee Installment')

@section('content')
<div class="container-fluid py-4" style="max-width:700px">
    <a href="{{ route('admission.fee-installments.index', $program) }}" class="text-muted small mb-3 d-inline-block"><i class="bi bi-arrow-left"></i> Back to Installments</a>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Edit Installment — {{ $program->name }}</div>
        <div class="card-body">
            <form action="{{ route('admission.fee-installments.update', $feeInstallment) }}" method="POST">
                @csrf @method('PUT')
                @include('admission.fee-installments._form', ['installment' => $feeInstallment])
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Update Installment</button>
                    <a href="{{ route('admission.fee-installments.index', $program) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
