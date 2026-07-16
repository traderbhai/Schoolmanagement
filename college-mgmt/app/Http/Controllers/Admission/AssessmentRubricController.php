<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\AdmissionAssessmentRubric;
use App\Services\AdmissionAccessPolicyService;
use Illuminate\Http\Request;

class AssessmentRubricController extends Controller
{
    public function index()
    {
        return view('admission.v0036.assessment-rubrics', [
            'rubrics' => AdmissionAssessmentRubric::with('criteria')->orderBy('assessment_type')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        app(AdmissionAccessPolicyService::class)->authorizeApproveAdmission($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'assessment_type' => ['required', 'string', 'max:80'],
            'minimum_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $rubric = AdmissionAssessmentRubric::create($data + [
            'minimum_score' => $data['minimum_score'] ?? 50,
            'recommendation_options' => ['recommended', 'waitlist', 'not_recommended'],
            'evaluator_instructions' => 'Score each criterion and add comments for any weak or exceptional performance.',
            'created_by' => $request->user()->id,
        ]);

        foreach ([
            ['Communication', 25, 1, true],
            ['Subject Clarity', 35, 1.2, true],
            ['Problem Solving', 25, 1, false],
            ['Admission Fit', 15, 1, true],
        ] as $index => [$name, $max, $weight, $comment]) {
            $rubric->criteria()->create([
                'name' => $name,
                'max_score' => $max,
                'weight' => $weight,
                'requires_comment' => $comment,
                'sort_order' => $index + 1,
            ]);
        }

        return back()->with('success', 'Rubric created with default criteria.');
    }
}
