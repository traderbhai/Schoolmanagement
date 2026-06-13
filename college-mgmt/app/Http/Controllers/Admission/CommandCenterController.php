<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionCommandCenterService;
use Illuminate\Http\Request;

class CommandCenterController extends Controller
{
    public function __invoke(Request $request, AdmissionCommandCenterService $service)
    {
        $filters = $request->only(['program_id', 'batch_id', 'source', 'priority', 'target_seats']);
        $dashboard = $service->dashboard($request->user(), array_filter($filters, fn ($value) => $value !== null && $value !== ''));

        return view('admission.v003.command-center', compact('dashboard', 'filters'));
    }
}
