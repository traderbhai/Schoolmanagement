@extends('layouts.admin')

@section('title', 'Scholarship Schemes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Scholarship Schemes</h2>
        <p class="text-muted mb-0">Configure applicant scholarship eligibility, capacity, and award limits.</p>
    </div>
    <a href="{{ route('admission.scholarship-schemes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Scheme
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">
        {{ session('success') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        {{ session('error') }}
        <button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4">
        <div class="fw-semibold">Scholarship scheme needs attention.</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($schemes->isEmpty())
            <div class="text-center py-5 px-3">
                <i class="bi bi-award fs-1 text-muted"></i>
                <h5 class="mt-3 mb-1">No scholarship schemes configured yet</h5>
                <p class="text-muted mb-3">
                    Create at least one active scheme before Admission staff can award scholarships from applicant profiles.
                </p>
                <a href="{{ route('admission.scholarship-schemes.create') }}" class="btn btn-primary">Add First Scheme</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">Admission scholarship schemes and eligibility rules</caption>
                    <thead class="bg-light">
                        <tr>
                            <th scope="col">Scheme</th>
                            <th scope="col">Code</th>
                            <th scope="col">Type</th>
                            <th scope="col">Program</th>
                            <th scope="col">Eligibility</th>
                            <th scope="col" class="text-end">Max Amount (Rs.)</th>
                            <th scope="col" class="text-center">Seats</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schemes as $scheme)
                            <tr class="{{ $scheme->is_active ? '' : 'text-muted' }}">
                                <td class="fw-semibold">{{ $scheme->name }}</td>
                                <td class="font-monospace small">{{ $scheme->scheme_code }}</td>
                                <td><span class="{{ $scheme->type_badge }}">{{ $scheme->type_label }}</span></td>
                                <td>{{ $scheme->program->name ?? 'All Programs' }}</td>
                                <td class="small">
                                    @if($scheme->min_cgpa)
                                        <div>CGPA >= {{ $scheme->min_cgpa }}</div>
                                    @endif
                                    @if($scheme->max_family_income)
                                        <div>Income <= Rs. {{ number_format((float) $scheme->max_family_income, 0) }}</div>
                                    @endif
                                    @if($scheme->requires_document)
                                        <div>Proof required</div>
                                    @endif
                                    @if(!$scheme->min_cgpa && !$scheme->max_family_income && !$scheme->requires_document)
                                        <span class="text-muted">Text criteria only</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">Rs. {{ number_format($scheme->max_amount, 0) }}</td>
                                <td class="text-center">
                                    @if($scheme->available_seats)
                                        {{ $scheme->seatsRemaining() }} / {{ $scheme->available_seats }}
                                    @else
                                        <span class="text-muted">Unlimited</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($scheme->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admission.scholarship-schemes.edit', $scheme) }}" class="btn btn-sm btn-outline-secondary" title="Edit scheme">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admission.scholarship-schemes.toggle', $scheme) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $scheme->is_active ? 'warning' : 'success' }}" title="{{ $scheme->is_active ? 'Deactivate scheme' : 'Activate scheme' }}">
                                            <i class="bi bi-{{ $scheme->is_active ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $schemes->render() }}</div>
        @endif
    </div>
</div>
@endsection
