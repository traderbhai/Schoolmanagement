<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AccessControl;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $routes = $this->routeQuery()->get();
        $vehicles = $this->vehicleQuery()->get();
        $assignments = $this->assignmentQuery($request)->paginate(20)->withQueryString();
        $students = Student::with('user')->where('status', 'active')->orderBy('enrollment_number')->get();

        $stats = [
            'routes' => $routes->where('is_active', true)->count(),
            'vehicles' => $vehicles->where('status', 'active')->count(),
            'active_assignments' => TransportAssignment::where('status', 'active')->count(),
            'monthly_revenue' => TransportAssignment::where('status', 'active')->sum('monthly_fee'),
        ];

        return view('admin.transport.index', compact('routes', 'vehicles', 'assignments', 'students', 'stats'));
    }

    public function exportRoutes(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $routes = $this->routeQuery()->get();
        $this->recordExportActivity('routes and stops', $routes->count(), $request);

        $rows = [['Route', 'Code', 'Start Point', 'End Point', 'Distance Km', 'Monthly Fee', 'Active', 'Stops']];
        foreach ($routes as $route) {
            $rows[] = [
                $route->name,
                $route->code,
                $route->start_point,
                $route->end_point,
                $route->distance_km,
                $route->monthly_fee,
                $route->is_active ? 'Yes' : 'No',
                $route->stops->map(fn(TransportStop $stop) => "{$stop->sequence}. {$stop->name}")->implode(' | '),
            ];
        }

        return $this->csvDownload('transport-routes-' . now()->format('Ymd') . '.csv', $rows);
    }

    public function exportVehicles(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $vehicles = $this->vehicleQuery()->get();
        $this->recordExportActivity('vehicles', $vehicles->count(), $request);

        return $this->csvDownload('transport-vehicles-' . now()->format('Ymd') . '.csv', [
            ['Registration', 'Type', 'Capacity', 'Driver', 'Driver Phone', 'Attendant', 'Status', 'Active Assignments'],
            ...$vehicles->map(fn(TransportVehicle $vehicle) => [
                $vehicle->registration_number,
                $vehicle->vehicle_type,
                $vehicle->capacity,
                $vehicle->driver_name,
                $vehicle->driver_phone,
                $vehicle->attendant_name,
                $vehicle->status,
                $vehicle->active_assignments_count,
            ])->all(),
        ]);
    }

    public function exportAssignments(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $assignments = $this->assignmentQuery($request)->get();
        $this->recordExportActivity('active assignments', $assignments->count(), $request);

        return $this->csvDownload('transport-assignments-' . now()->format('Ymd') . '.csv', [
            ['Student', 'Enrollment', 'Route', 'Stop', 'Vehicle', 'Start Date', 'Monthly Fee', 'Status', 'Notes'],
            ...$assignments->map(fn(TransportAssignment $assignment) => [
                $assignment->student?->user?->name,
                $assignment->student?->enrollment_number,
                $assignment->route?->name,
                $assignment->stop?->name,
                $assignment->vehicle?->registration_number,
                $assignment->start_date?->toDateString(),
                $assignment->monthly_fee,
                $assignment->status,
                $assignment->notes,
            ])->all(),
        ]);
    }

    public function routeStore(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:30|unique:transport_routes,code',
            'start_point' => 'required|string|max:120',
            'end_point' => 'required|string|max:120',
            'distance_km' => 'nullable|numeric|min:0',
            'monthly_fee' => 'required|numeric|min:0',
        ]);

        TransportRoute::create($data + ['is_active' => true]);

        return back()->with('success', 'Transport route created.');
    }

    public function stopStore(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'transport_route_id' => 'required|exists:transport_routes,id',
            'name' => 'required|string|max:120',
            'sequence' => 'required|integer|min:1|max:999',
            'pickup_time' => 'nullable|date_format:H:i',
            'drop_time' => 'nullable|date_format:H:i',
            'monthly_fee_override' => 'nullable|numeric|min:0',
        ]);

        $route = TransportRoute::findOrFail($data['transport_route_id']);
        if (! $route->is_active) {
            return back()
                ->withErrors(['transport_route_id' => 'Stops can be added only to active transport routes.'])
                ->withInput();
        }

        if (TransportStop::where('transport_route_id', $route->id)
            ->where('sequence', $data['sequence'])
            ->exists()) {
            return back()
                ->withErrors(['sequence' => 'Another stop already uses this sequence on the selected route.'])
                ->withInput();
        }

        TransportStop::create($data + ['is_active' => true]);

        return back()->with('success', 'Transport stop added.');
    }

    public function vehicleStore(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'registration_number' => 'required|string|max:30|unique:transport_vehicles,registration_number',
            'vehicle_type' => 'required|string|max:40',
            'capacity' => 'required|integer|min:1|max:200',
            'driver_name' => 'required|string|max:120',
            'driver_phone' => 'nullable|string|max:30',
            'attendant_name' => 'nullable|string|max:120',
            'status' => 'required|in:active,maintenance,inactive',
        ]);

        TransportVehicle::create($data);

        return back()->with('success', 'Transport vehicle added.');
    }

    public function vehicleUpdate(Request $request, TransportVehicle $vehicle)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'registration_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('transport_vehicles', 'registration_number')->ignore($vehicle->id),
            ],
            'vehicle_type' => 'required|string|max:40',
            'capacity' => 'required|integer|min:1|max:200',
            'driver_name' => 'required|string|max:120',
            'driver_phone' => 'nullable|string|max:30',
            'attendant_name' => 'nullable|string|max:120',
            'status' => 'required|in:active,maintenance,inactive',
        ]);

        $activeAssignments = TransportAssignment::where('transport_vehicle_id', $vehicle->id)
            ->where('status', 'active')
            ->count();

        if ((int) $data['capacity'] < $activeAssignments) {
            return back()->withErrors([
                'capacity' => "Vehicle capacity cannot be lower than the current active assignment count ({$activeAssignments}).",
            ])->withInput();
        }

        if ($data['status'] !== 'active' && $activeAssignments > 0) {
            return back()->withErrors([
                'status' => "Vehicle cannot be marked {$data['status']} while {$activeAssignments} active assignment(s) are linked.",
            ])->withInput();
        }

        $vehicle->update($data);

        return back()->with('success', 'Transport vehicle updated.');
    }

    public function assignmentStore(Request $request)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'transport_stop_id' => 'nullable|exists:transport_stops,id',
            'transport_vehicle_id' => 'nullable|exists:transport_vehicles,id',
            'start_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (TransportAssignment::where('student_id', $data['student_id'])->where('status', 'active')->exists()) {
            return back()->withErrors(['student_id' => 'Student already has an active transport assignment.']);
        }

        $student = Student::findOrFail($data['student_id']);
        if ($student->status !== 'active') {
            return back()->withErrors(['student_id' => 'Only active students can receive transport assignments.']);
        }

        $route = TransportRoute::findOrFail($data['transport_route_id']);
        if (!$route->is_active) {
            return back()->withErrors(['transport_route_id' => 'Transport route is inactive.']);
        }

        $stop = null;
        if (!empty($data['transport_stop_id'])) {
            $stop = TransportStop::findOrFail($data['transport_stop_id']);
            if ((int) $stop->transport_route_id !== (int) $route->id) {
                return back()->withErrors(['transport_stop_id' => 'Selected stop does not belong to the selected route.']);
            }
            if (!$stop->is_active) {
                return back()->withErrors(['transport_stop_id' => 'Selected stop is inactive.']);
            }
        }

        if (!empty($data['transport_vehicle_id'])) {
            $vehicle = TransportVehicle::findOrFail($data['transport_vehicle_id']);
            if ($vehicle->status !== 'active') {
                return back()->withErrors(['transport_vehicle_id' => 'Only active vehicles can be assigned.']);
            }

            $activeCount = TransportAssignment::where('transport_vehicle_id', $vehicle->id)
                ->where('status', 'active')
                ->count();
            if ($activeCount >= $vehicle->capacity) {
                return back()->withErrors(['transport_vehicle_id' => 'Vehicle has reached its assigned student capacity.']);
            }
        }

        TransportAssignment::create([
            'student_id' => $data['student_id'],
            'transport_route_id' => $route->id,
            'transport_stop_id' => $stop?->id,
            'transport_vehicle_id' => $data['transport_vehicle_id'] ?? null,
            'start_date' => $data['start_date'],
            'monthly_fee' => $stop?->monthly_fee_override ?? $route->monthly_fee,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Student transport assignment created.');
    }

    public function assignmentEnd(Request $request, TransportAssignment $assignment)
    {
        $this->authorizeTransportOperations($request);

        $data = $request->validate([
            'end_date' => 'required|date|before_or_equal:today',
        ]);

        if ($assignment->status !== 'active') {
            return back()->with('error', 'Only active transport assignments can be ended.');
        }

        if ($assignment->start_date && $assignment->start_date->greaterThan($data['end_date'])) {
            return back()->withErrors(['end_date' => 'End date cannot be before the assignment start date.']);
        }

        $assignment->update([
            'status' => 'inactive',
            'end_date' => $data['end_date'],
        ]);

        return back()->with('success', 'Transport assignment ended.');
    }

    private function authorizeTransportOperations(Request $request): void
    {
        abort_unless(
            $request->user() && AccessControl::canManageTransportOperations($request->user()),
            403
        );
    }

    private function routeQuery()
    {
        return TransportRoute::with('stops')->orderBy('name');
    }

    private function vehicleQuery()
    {
        return TransportVehicle::withCount([
            'assignments as active_assignments_count' => fn($query) => $query->where('status', 'active'),
        ])->orderBy('registration_number');
    }

    private function assignmentQuery(Request $request)
    {
        $query = TransportAssignment::with(['student.user', 'route', 'stop', 'vehicle'])
            ->where('status', 'active');

        if ($request->filled('assignment_search')) {
            $search = $request->assignment_search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('student.user', fn($user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('student', fn($student) => $student->where('enrollment_number', 'like', "%{$search}%"))
                    ->orWhereHas('route', fn($route) => $route->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('stop', fn($stop) => $stop->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('vehicle', fn($vehicle) => $vehicle->where('registration_number', 'like', "%{$search}%"));
            });
        }

        return $query->latest();
    }

    private function csvDownload(string $filename, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function recordExportActivity(string $surface, int $rowCount, Request $request): void
    {
        $filters = $request->query();
        $filterSummary = empty($filters) ? 'none' : json_encode($filters, JSON_UNESCAPED_SLASHES);

        ActivityLog::record('export', "Transport {$surface} exported: {$rowCount} rows; filters={$filterSummary}");
    }
}
