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

<div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <div class="fw-semibold">Transport operating sequence</div>
            <div class="small text-muted">Set up routes, stops, vehicles, and assignments in order so billing and student transport visibility stay consistent.</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-light">1. Create route</span>
            <span class="badge text-bg-light">2. Add stops</span>
            <span class="badge text-bg-light">3. Add vehicle</span>
            <span class="badge text-bg-light">4. Assign students</span>
            <span class="badge text-bg-light">5. Export/review fleet</span>
        </div>
    </div>
</div>

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
                    <input aria-label="Route name" name="name" class="form-control" placeholder="Route name" required>
                    <input aria-label="Transport route code" name="code" class="form-control" placeholder="Code, e.g. R-01" required>
                    <input aria-label="Start point" name="start_point" class="form-control" placeholder="Start point" required>
                    <input aria-label="End point" name="end_point" class="form-control" placeholder="End point" required>
                    <div class="row g-2">
                        <div class="col-6"><input aria-label="KM" name="distance_km" type="number" step="0.01" min="0" class="form-control" placeholder="KM"></div>
                        <div class="col-6"><input aria-label="Monthly fee" name="monthly_fee" type="number" step="0.01" min="0" class="form-control" placeholder="Monthly fee" required></div>
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
                    <select aria-label="Transport Route" name="transport_route_id" class="form-select" required>
                        <option value="">- Select Route -</option>
                        @foreach($routes as $route)
                            @continue(!$route->is_active)
                            <option value="{{ $route->id }}">{{ $route->name }} ({{ $route->code }})</option>
                        @endforeach
                    </select>
                    <input aria-label="Stop name" name="name" class="form-control" placeholder="Stop name" required>
                    <input aria-label="Sequence" name="sequence" type="number" min="1" max="999" value="1" class="form-control" placeholder="Sequence" required>
                    <div class="row g-2">
                        <div class="col-6"><input aria-label="Pickup Time" name="pickup_time" type="time" class="form-control"></div>
                        <div class="col-6"><input aria-label="Drop Time" name="drop_time" type="time" class="form-control"></div>
                    </div>
                    <input aria-label="Stop fee override" name="monthly_fee_override" type="number" step="0.01" min="0" class="form-control" placeholder="Stop fee override">
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
                    <input aria-label="Registration number" name="registration_number" class="form-control" placeholder="Registration number" required>
                    <input aria-label="Vehicle type" name="vehicle_type" class="form-control" value="bus" placeholder="Vehicle type" required>
                    <input aria-label="Capacity" name="capacity" type="number" min="1" max="200" class="form-control" placeholder="Capacity" required>
                    <input aria-label="Driver name" name="driver_name" class="form-control" placeholder="Driver name" required>
                    <input aria-label="Driver phone" name="driver_phone" class="form-control" placeholder="Driver phone">
                    <input aria-label="Attendant name" name="attendant_name" class="form-control" placeholder="Attendant name">
                    <select aria-label="Status" name="status" class="form-select" required>
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
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                <span>Vehicle Fleet</span>
                <a href="{{ route('admin.transport.vehicles.export') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
            </div>
            <div class="px-3 pb-2 text-muted small">Showing {{ $vehicles->count() }} vehicle record(s).</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Vehicle</th>
                        <th scope="col">Capacity</th>
                        <th scope="col">Driver / Contact</th>
                        <th scope="col">Status</th>
                        <th scope="col">Active Assignments</th>
                        <th scope="col" class="text-end pe-3">Update</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        <tr>
                            <form method="POST" action="{{ route('admin.transport.vehicles.update', $vehicle) }}">
                                @csrf
                                @method('PATCH')
                                <td>
                                    <input aria-label="Registration Number" name="registration_number" class="form-control form-control-sm mb-1" value="{{ old('registration_number', $vehicle->registration_number) }}" required>
                                    <input aria-label="Vehicle Type" name="vehicle_type" class="form-control form-control-sm" value="{{ old('vehicle_type', $vehicle->vehicle_type) }}" required>
                                </td>
                                <td style="width:110px">
                                    <input aria-label="Capacity" name="capacity" type="number" min="1" max="200" class="form-control form-control-sm" value="{{ old('capacity', $vehicle->capacity) }}" required>
                                </td>
                                <td>
                                    <input aria-label="Driver Name" name="driver_name" class="form-control form-control-sm mb-1" value="{{ old('driver_name', $vehicle->driver_name) }}" required>
                                    <div class="row g-1">
                                        <div class="col-6"><input aria-label="Driver Phone" name="driver_phone" class="form-control form-control-sm" value="{{ old('driver_phone', $vehicle->driver_phone) }}" placeholder="Driver phone"></div>
                                        <div class="col-6"><input aria-label="Attendant Name" name="attendant_name" class="form-control form-control-sm" value="{{ old('attendant_name', $vehicle->attendant_name) }}" placeholder="Attendant"></div>
                                    </div>
                                </td>
                                <td style="width:150px">
                                    <select aria-label="Status" name="status" class="form-select form-select-sm" required>
                                        @foreach(['active' => 'Active', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'] as $status => $label)
                                            <option value="{{ $status }}" @selected(old('status', $vehicle->status) === $status)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <span class="badge bg-{{ ($vehicle->active_assignments_count ?? 0) > 0 ? 'primary' : 'secondary' }}">
                                        {{ $vehicle->active_assignments_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary">Save route</button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <div class="fw-semibold text-dark">No vehicles are configured.</div>
                                <div class="small">Add active buses, vans, or other transport vehicles before assigning students to route capacity.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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
                <select aria-label="Student" name="student_id" class="form-select" required>
                    <option value="">- Select Student -</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}">{{ $student->user?->name }} ({{ $student->enrollment_number }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Route</label>
                <select aria-label="Transport Route" name="transport_route_id" class="form-select" required>
                    <option value="">- Route -</option>
                    @foreach($routes as $route)
                        @continue(!$route->is_active)
                        <option value="{{ $route->id }}">{{ $route->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Stop</label>
                <select aria-label="Transport Stop" name="transport_stop_id" class="form-select">
                    <option value="">- Stop -</option>
                    @foreach($routes as $route)
                        @foreach($route->stops as $stop)
                            @continue(!$route->is_active || !$stop->is_active)
                            <option value="{{ $stop->id }}">{{ $route->code }} - {{ $stop->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vehicle</label>
                <select aria-label="Transport Vehicle" name="transport_vehicle_id" class="form-select">
                    <option value="">- Vehicle -</option>
                    @foreach($vehicles as $vehicle)
                        @continue($vehicle->status !== 'active')
                        <option value="{{ $vehicle->id }}">{{ $vehicle->registration_number }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input aria-label="Start Date" type="date" name="start_date" value="{{ now()->toDateString() }}" class="form-control" required>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Assign</button>
            </div>
            <div class="col-12">
                <input aria-label="Notes, pickup instructions, or parent contact preferences" name="notes" class="form-control" placeholder="Notes, pickup instructions, or parent contact preferences">
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">Active Assignments</label>
                        <input aria-label="Search student, route, stop, or vehicle" type="search" name="assignment_search" value="{{ request('assignment_search') }}" class="form-control form-control-sm" placeholder="Search student, route, stop, or vehicle">
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('admin.transport.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <a href="{{ route('admin.transport.assignments.export', request()->only('assignment_search')) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download me-1"></i>Export Current View</a>
                    </div>
                </form>
                <div class="text-muted small mt-2">Showing {{ $assignments->total() }} active assignment record(s){{ request('assignment_search') ? ' for search: '.request('assignment_search') : '' }}.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Route / Stop</th>
                                <th scope="col">Vehicle</th>
                                <th scope="col" class="text-end">Monthly Fee</th>
                                <th aria-label="Actions" scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $assignment->student->user->name ?? 'Student not linked' }}</div>
                                        <div class="small text-muted">{{ $assignment->student->enrollment_number ?? 'Enrollment number pending' }}</div>
                                    </td>
                                    <td>
                                        {{ $assignment->route->name ?? 'Route not linked' }}
                                        <div class="small text-muted">{{ $assignment->stop->name ?? 'No stop selected' }}</div>
                                    </td>
                                    <td>{{ $assignment->vehicle->registration_number ?? 'Vehicle not assigned' }}</td>
                                    <td class="text-end">Rs. {{ number_format($assignment->monthly_fee, 2) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.transport.assignments.end', $assignment) }}" class="d-inline" onsubmit="return confirm('End transport assignment for {{ addslashes($assignment->student->user->name ?? 'this student') }} from today? Confirm route/stop removal, monthly fee impact, vehicle capacity release, and student communication before ending access.')">
                                            @csrf
                                            <input type="hidden" name="end_date" value="{{ now()->toDateString() }}">
                                            <button class="btn btn-sm btn-outline-danger">End</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <div class="fw-semibold text-dark">No active transport assignments match this view.</div>
                                        <div class="small">Assign an active student to an active route and stop above, or clear the search filter to review all current transport allocations.</div>
                                        <div class="mt-2">
                                            <a href="{{ route('admin.transport.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                        </div>
                                    </td>
                                </tr>
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
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                <span>Routes And Stops</span>
                <a href="{{ route('admin.transport.routes.export') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
            </div>
            <div class="px-3 pb-2 text-muted small">Showing {{ $routes->count() }} route record(s).</div>
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
                                <span class="text-muted">No stops configured yet. Add pickup/drop stops before assigning students to this route.</span>
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
