<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\AdmissionObjectionType;
use App\Services\AdmissionObjectionAnalyticsService;
use Illuminate\Http\Request;

class ObjectionAnalyticsController extends Controller
{
    public function index(AdmissionObjectionAnalyticsService $service)
    {
        return view('admission.v0037.objection-analytics', ['dashboard' => $service->dashboard()]);
    }

    public function store(Request $request, AdmissionObjectionAnalyticsService $service)
    {
        $type = AdmissionObjectionType::firstOrFail();
        $service->record(Lead::firstOrFail(), $type, $request->user(), 'Demo objection logged from analytics page.');

        return back()->with('success', 'Objection event logged.');
    }
}
