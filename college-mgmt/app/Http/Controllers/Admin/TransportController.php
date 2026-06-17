<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TransportAssignment;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransportController extends Controller
{
    public function index()
    {
        $routes = TransportRoute::with('stops')->orderBy('name')->get();
        $vehicles = TransportVehicle::withCount(['assignments as active_assignments_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('registration_number')
            ->get();
        $assignments = TransportAssignment::with(['student.user', 'route', 'stop', 'vehicle'])
            ->where('status', 'active')
            ->latest()
            ->paginate(20);
        $students = Student::with('user')->where('status', 'active')->orderBy('enrollment_number')->get();

        $stats = [
            'routes' => $routes->where('is_active', true)->count(),
            'vehicles' => $vehicles->where('status', 'active')->count(),
            'active_assignments' => TransportAssignment::where('status', 'active')->count(),
            'monthly_revenue' => TransportAssignment::where('status', 'active')->sum('monthly_fee'),
        ];

        return view('admin.transport.index', compact('routes', 'vehicles', 'assignments', 'students', 'stats'));
    }

    public function routeStore(Request $request)
    {
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
        $data = $request->validate([
            'transport_route_id' => 'required|exists:transport_routes,id',
            'name' => 'required|string|max:120',
            'sequence' => 'required|integer|min:1|max:999',
            'pickup_time' => 'nullable|date_format:H:i',
            'drop_time' => 'nullable|date_format:H:i',
            'monthly_fee_override' => 'nullable|numeric|min:0',
        ]);

        TransportStop::create($data + ['is_active' => true]);

        return back()->with('success', 'Transport stop added.');
    }

    public function vehicleStore(Request $request)
    {
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
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'transport_route_id' => 'required|exists:transport_routes,id',
            'transport_stop_id' => 'nullable|exists:transport_stops,id',
            'transport_vehicle_id' => 'nullable|exists:transport_vehicles,id',
            'start_date' => 'required|date',
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
}
