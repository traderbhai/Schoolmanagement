<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionCalendarService;
use Illuminate\Http\Request;

class AdmissionCalendarController extends Controller
{
    public function __invoke(Request $request, AdmissionCalendarService $calendar)
    {
        return view('admission.v0031.calendar', [
            'events' => $calendar->eventsFor($request->user(), $request->only(['from', 'to'])),
        ]);
    }
}
