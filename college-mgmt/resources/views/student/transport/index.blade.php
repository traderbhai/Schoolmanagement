@extends('layouts.student')

@section('title', 'My Transport')
@section('page-title', 'My Transport')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Transport</li>
@endsection

@section('content')
@if($assignment)
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Route</div>
                <div class="fs-5 fw-bold">{{ $assignment->route->name ?? '-' }}</div>
                <div class="small text-muted">{{ $assignment->route->start_point ?? '-' }} to {{ $assignment->route->end_point ?? '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Pickup Stop</div>
                <div class="fs-5 fw-bold">{{ $assignment->stop->name ?? 'Assigned by transport office' }}</div>
                <div class="small text-muted">
                    Pickup {{ $assignment->stop?->pickup_time ?? '-' }} / Drop {{ $assignment->stop?->drop_time ?? '-' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Monthly Fee</div>
                <div class="fs-5 fw-bold">Rs. {{ number_format($assignment->monthly_fee, 2) }}</div>
                <div class="small text-muted">Started {{ $assignment->start_date?->format('d M Y') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Vehicle And Contact</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted small">Vehicle</div>
                <div class="fw-semibold">{{ $assignment->vehicle->registration_number ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Driver</div>
                <div class="fw-semibold">{{ $assignment->vehicle->driver_name ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Driver Phone</div>
                <div class="fw-semibold">{{ $assignment->vehicle->driver_phone ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Attendant</div>
                <div class="fw-semibold">{{ $assignment->vehicle->attendant_name ?? '-' }}</div>
            </div>
        </div>
        @if($assignment->notes)
            <div class="alert alert-info mt-3 mb-0">{{ $assignment->notes }}</div>
        @endif
    </div>
</div>
@else
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body text-center py-5">
        <i class="bi bi-bus-front fs-1 text-muted d-block mb-2"></i>
        <h5>No Active Transport Assignment</h5>
        <p class="text-muted mb-0">Contact the transport office if you need campus bus pickup, route allocation, stop changes, or fee clarification.</p>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Transport History</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Route</th>
                        <th>Stop</th>
                        <th>Vehicle</th>
                        <th>Period</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $item)
                        <tr>
                            <td>{{ $item->route->name ?? '-' }}</td>
                            <td>{{ $item->stop->name ?? '-' }}</td>
                            <td>{{ $item->vehicle->registration_number ?? '-' }}</td>
                            <td>{{ $item->start_date?->format('d M Y') }} - {{ $item->end_date?->format('d M Y') ?? 'Current' }}</td>
                            <td>
                                @if($item->status === 'active')
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark mb-1">No transport history yet</div>
                                <div class="small">
                                    Active and past bus assignments will appear here after the transport office assigns you to a route, stop, and vehicle.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
