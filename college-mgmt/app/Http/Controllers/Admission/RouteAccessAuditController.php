<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionRouteAccessAuditService;
use Illuminate\Http\Request;

class RouteAccessAuditController extends Controller
{
    public function __invoke(Request $request, AdmissionRouteAccessAuditService $service)
    {
        return view('admission.v0037.route-access-audit', [
            'dashboard' => $service->dashboard($request->user()),
        ]);
    }
}
