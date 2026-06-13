<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionAssessmentControlRoomService;
use Illuminate\Http\Request;

class AssessmentControlRoomController extends Controller
{
    public function __invoke(Request $request, AdmissionAssessmentControlRoomService $service)
    {
        return view('admission.v0036.assessment-control-room', [
            'dashboard' => $service->dashboard($request->user(), $request->only(['program_id'])),
        ]);
    }
}
