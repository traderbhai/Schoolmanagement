<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdmissionCounsellorPerformanceService;
use Illuminate\Http\Request;

class CounsellorPerformanceController extends Controller
{
    public function index(Request $request, AdmissionCounsellorPerformanceService $service)
    {
        return view('admission.v0037.counsellor-performance', [
            'dashboard' => $service->dashboard($request->user()),
        ]);
    }

    public function coach(Request $request, User $counsellor, AdmissionCounsellorPerformanceService $service)
    {
        $data = $request->validate([
            'review_type' => ['nullable', 'string', 'max:80'],
            'score_band' => ['required', 'in:excellent,on_track,needs_coaching'],
            'strengths' => ['nullable', 'string'],
            'improvement_areas' => ['nullable', 'string'],
            'action_plan' => ['required', 'string'],
            'next_review_at' => ['nullable', 'date'],
        ]);

        $service->addCoachingNote($counsellor, $request->user(), $data);

        return back()->with('success', 'Coaching note added for ' . $counsellor->name . '.');
    }
}
