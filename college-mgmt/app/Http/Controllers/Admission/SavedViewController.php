<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionSavedViewService;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    public function index(AdmissionSavedViewService $service, Request $request)
    {
        return view('admission.v0037.saved-views', [
            'views' => $service->forSurface($request->query('surface', 'assessment_control_room'), $request->user()),
        ]);
    }

    public function store(Request $request, AdmissionSavedViewService $service)
    {
        $data = $request->validate(['surface' => ['required', 'string'], 'name' => ['required', 'string'], 'filters_json' => ['nullable', 'json']]);
        $service->save($data['surface'], $data['name'], json_decode($data['filters_json'] ?: '{}', true), $request->user());

        return back()->with('success', 'Saved view stored.');
    }
}
