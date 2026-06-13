@extends('layouts.admin')

@section('title', 'Transport Management')
@section('page-title', 'Transport Management')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active">Transport</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active Routes</div><div class="fs-3 fw-bold">{{ $stats['routes'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active Vehicles</div><div class="fs-3 fw-bold">{{ $stats['vehicles'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Assigned Students</div><div class="fs-3 fw-bold">{{ $stats['active_assignments'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Monthly Transport Billing</div><div class="fs-3 fw-bold">Rs. {{ number_format($stats['monthly_revenue'], 2) }}</div></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Create Route</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.transport.routes.store') }}" class="vstack gap-3">
                    @csrf
                    <input name="name" class="form-control" placeholder="Route name" required>
                    <input name="code" class="form-control" placeholder="Code, e.g. R-01" required>
                    <input name="start_point" class="form-control" placeholder="Start point" required>
                    <input name="end_point" class="form-control" placeholder="End point" required>
                    <div class="row g-2">
                        <div class="col-6"><input name="distance_km" type="number" step="0.01" min="0" class="form-control" placeholder="KM"></div>
                        <div class="col-6"><input name="monthly_fee" type="number" step="0.01" min="0" class="form-control" placeholder="Monthly fee" required></div>
                    </div>
                    <button class="btn btn-primary">Save Route</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Add Stop</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.transport.stops.store') }}" class="vstack gap-3">
                    @csrf
                    <select name="transport_route_id" class="form-select" required>
                        <option value="">- Select Route -</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }} ({{ $route->code }})</option>
                        @endforeach
                    </select>
                    <input name="name" class="form-control" placeholder="Stop name" required>
                    <input name="sequence" type="number" min="1" max="999" value="1" class="form-control" placeholder="Sequence" required>
                    <div class="row g-2">
                        <div class="col-6"><input name="pickup_time" type="time" class="form-control"></div>
                        <div class="col-6"><input name="drop_time" type="time" class="form-control"></div>
                    </div>
                    <input name="monthly_fee_override" type="number" step="0.01" min="0" class="form-control" placeholder="Stop fee override">
                    <button class="btn btn-primary">Add Stop</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">Add Vehicle</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.transport.vehicles.store') }}" class="vstack gap-3">
                    @csrf
                    <input name="registration_number" class="form-control" placeholder="Registration number" required>
                    <input name="vehicle_type" class="form-control" value="bus" placeholder="Vehicle type" required>
                    <input name="capacity" type="number" min="1" max="200" class="form-control" placeholder="Capacity" required>
                    <input name="driver_name" class="form-control" placeholder="Driver name" required>
                    <input name="driver_phone" class="form-control" placeholder="Driver phone">
                    <input name="attendant_name" class="form-control" placeholder="Attendant name">
                    <select name="status" class="form-select" required>
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <button class="btn btn-primary">Add Vehicle</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">Assign Student To Transport</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.transport.assignments.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">- Select Student -</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->user?->name }} ({{ $student->enrollment_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Route</label>
                <select name="transport_route_id" class="form-select" required>
                    <option value="">- Route -</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stop</label>
                <select name="transport_stop_id" class="form-select">
                    <option value="">- Stop -</option>
                    @foreach($routes as $route)
                        @foreach($route->stops as $stop)
                            <option value="{{ $stop->id }}">{{ $route->code }} - {{ $stop->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vehicle</label>
                <select name="transport_vehicle_id" class="form-select">
                    <option value="">- Vehicle -</option>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" value="{{ now()->toDateString() }}" class="form-control" required>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Assign</button>
            </div>
            <div class="col-12">
                <input name="notes" class="form-control" placeholder="Notes, pickup instructions, or parent contact preferences">
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Active Assignments</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Route / Stop</th>
                                <th>Vehicle</th>
                                <th class="text-end">Monthly Fee</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->student->user->name ?? 'Student' }}</div>
                                        <div class="small text-muted">{{ $assignment->student->enrollment_number ?? '' }}</div>
                                    </td>
                                    <td>
                                        {{ $assignment->route->name ?? '-' }}
                                        <div class="small text-muted">{{ $assignment->stop->name ?? 'No stop selected' }}</div>
                                    </td>
                                    <td>{{ $assignment->vehicle->registration_number ?? '-' }}</td>
                                    <td class="text-end">Rs. {{ number_format($assignment->monthly_fee, 2) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.transport.assignments.end', $assignment) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="end_date" value="{{ now()->toDateString() }}">
                                            <button class="btn btn-sm btn-outline-danger">End</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No active transport assignments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($assignments->hasPages())
                <div class="card-footer bg-transparent">{{ $assignments->links() }}</div>
            @endif
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold">Routes And Stops</div>
            <div class="card-body">
                @forelse($routes as $route)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">{{ $route->name }} <span class="text-muted small">({{ $route->code }})</span></div>
                                <div class="small text-muted">{{ $route->start_point }} to {{ $route->end_point }}</div>
                            </div>
                            <div class="text-end small">Rs. {{ number_format($route->monthly_fee, 2) }}</div>
                        </div>
                        <div class="mt-2 small">
                            @forelse($route->stops as $stop)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $stop->sequence }}. {{ $stop->name }}</span>
                            @empty
                                <span class="text-muted">No stops yet.</span>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Create a route before adding stops or assignments.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
