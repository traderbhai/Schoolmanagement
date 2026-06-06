<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;

class ApplicantCategoryController extends Controller
{
    public function edit(Applicant $applicant)
    {
        return view('admission.applicants.category-form', compact('applicant'));
    }

    public function update(Request $request, Applicant $applicant)
    {
        $validated = $request->validate([
            'category'                  => 'nullable|in:general,obc,obc_ncl,sc,st,ews,pwd,nri',
            'sub_category'              => 'nullable|string|max:100',
            'is_pwd'                    => 'nullable|boolean',
            'pwd_certificate_number'    => 'nullable|string|max:100',
            'entrance_exam_name'        => 'nullable|string|max:255',
            'entrance_exam_roll_number' => 'nullable|string|max:100',
            'entrance_exam_score'       => 'nullable|numeric|min:0|max:999999.99',
            'entrance_exam_rank'        => 'nullable|integer|min:1',
            'entrance_exam_date'        => 'nullable|date|before_or_equal:today',
        ]);

        $validated['is_pwd'] = $request->boolean('is_pwd');

        $applicant->update($validated);

        return redirect()->route('admission.applicants.show', $applicant)
            ->with('success', 'Category and exam details updated successfully.');
    }
}
