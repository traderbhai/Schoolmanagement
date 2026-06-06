@extends('layouts.admin')

@section('title', 'Scholarship Schemes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Scholarship Schemes</h2>
        <p class="text-muted mb-0">AICTE, government, and institutional scholarship schemes for applicants</p>
    </div>
    <a href="{{ route('admission.scholarship-schemes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Scheme
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($schemes->isEmpty())
            <div class="text-center py-5">
                <i class="bi bi-award fs-1 text-muted"></i>
                <p class="text-muted mt-3">No scholarship schemes configured yet.</p>
                <a href="{{ route('admission.scholarship-schemes.create') }}" class="btn btn-primary">Add First Scheme</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Scheme</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Program</th>
                            <th class="text-end">Max Amount (₹)</th>
                            <th class="text-center">Seats</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schemes as $scheme)
                        <tr class="{{ $scheme->is_active ? '' : 'text-muted' }}">
                            <td class="fw-semibold">{{ $scheme->name }}</td>
                            <td class="font-monospace small">{{ $scheme->scheme_code }}</td>
                            <td><span class="{{ $scheme->type_badge }}">{{ $scheme->type_label }}</span></td>
                            <td>{{ $scheme->program->name ?? 'All Programs' }}</td>
                            <td class="text-end fw-semibold">₹{{ number_format($scheme->max_amount, 0) }}</td>
                            <td class="text-center">
                                @if($scheme->available_seats)
                                    {{ $scheme->seatsRemaining() }} / {{ $scheme->available_seats }}
                                @else
                                    <span class="text-muted">—</span>
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
                                <a href="{{ route('admission.scholarship-schemes.edit', $scheme) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admission.scholarship-schemes.toggle', $scheme) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-{{ $scheme->is_active ? 'warning' : 'success' }}">
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
