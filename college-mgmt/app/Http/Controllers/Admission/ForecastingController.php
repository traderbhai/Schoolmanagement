<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionForecastSnapshot;
use App\Services\AdmissionForecastingService;
use Illuminate\Http\Request;

class ForecastingController extends Controller
{
    public function index(Request $request, AdmissionForecastingService $service)
    {
        $filters = array_filter($request->only(['program_id', 'batch_id', 'source', 'target_seats']), fn ($value) => $value !== null && $value !== '');
        $snapshot = $request->boolean('refresh')
            ? $service->snapshot($filters, $request->user())
            : AdmissionForecastSnapshot::latest()->first();

        return view('admission.v003.forecasting', [
            'snapshot' => $snapshot,
            'snapshots' => AdmissionForecastSnapshot::latest()->limit(20)->get(),
        ]);
    }

    public function snapshot(Request $request, AdmissionForecastingService $service)
    {
        $service->snapshot($request->only(['program_id', 'batch_id', 'source', 'target_seats']), $request->user());

        return back()->with('success', 'Forecast snapshot generated.');
    }
}
