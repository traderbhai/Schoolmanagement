<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionCounsellorDeskService;
use Illuminate\Http\Request;

class CounsellorDeskController extends Controller
{
    public function __invoke(Request $request, AdmissionCounsellorDeskService $service)
    {
        return view('admission.v0036.counsellor-desk', [
            'desk' => $service->dashboard($request->user(), $request->all()),
        ]);
    }
}
