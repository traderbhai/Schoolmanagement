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
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));

        return view('admission.v0031.manager-reviews', [
            'reviews' => $service->queryFor($request->user(), $request->only(['status', 'review_type']))
                ->paginate($perPage)
                ->withQueryString(),
        ]);
    }

    public function resolve(AdmissionManagerReview $review, Request $request, AdmissionManagerReviewService $service)
    {
        abort_unless($service->canAccess($review, $request->user()), 403);
        $data = $request->validate(['resolution_notes' => ['required', 'string', 'min:3']]);
        $service->resolve($review, $request->user(), $data['resolution_notes']);

        return back()->with('success', 'Manager review resolved.');
    }
}
