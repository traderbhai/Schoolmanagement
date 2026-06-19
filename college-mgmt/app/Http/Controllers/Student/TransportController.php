<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;

class TransportController extends Controller
{
    public function index()
    {
        $student = Student::where('user_id', auth()->id())->first();
        if (!$student) {
            return redirect()->route('student.dashboard');
        }

        $assignment = $student->status === 'active'
            ? $student->activeTransportAssignment()
                ->with(['route', 'stop', 'vehicle'])
                ->whereDate('start_date', '<=', now()->toDateString())
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
                ->whereHas('route', fn ($route) => $route->where('is_active', true))
                ->where(function ($query) {
                    $query->whereNull('transport_stop_id')
                        ->orWhereHas('stop', fn ($stop) => $stop->where('is_active', true));
                })
                ->where(function ($query) {
                    $query->whereNull('transport_vehicle_id')
                        ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('status', 'active'));
                })
                ->first()
            : null;

        $history = $student->transportAssignments()
            ->with(['route', 'stop', 'vehicle'])
            ->latest()
            ->limit(10)
            ->get();

        return view('student.transport.index', compact('assignment', 'history'));
    }
}
