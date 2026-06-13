<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionManagerReview;
use App\Services\AdmissionManagerReviewService;
use Illuminate\Http\Request;

class ManagerReviewController extends Controller
{
    public function index(Request $request, AdmissionManagerReviewService $service)
    {
        return view('admission.v0031.manager-reviews', [
            'reviews' => $service->queueFor($request->user(), $request->only(['status', 'review_type'])),
        ]);
    }

    public function resolve(AdmissionManagerReview $review, Request $request, AdmissionManagerReviewService $service)
    {
        $data = $request->validate(['resolution_notes' => ['required', 'string', 'min:3']]);
        $service->resolve($review, $request->user(), $data['resolution_notes']);

        return back()->with('success', 'Manager review resolved.');
    }
}
