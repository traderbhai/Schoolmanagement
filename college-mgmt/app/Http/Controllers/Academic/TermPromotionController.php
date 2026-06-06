<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\TermPromotion;
use App\Models\Student;
use Illuminate\Http\Request;

class TermPromotionController extends Controller
{
    public function index()
    {
        $promotions = TermPromotion::with(['student', 'currentTerm', 'promotedToTerm'])->paginate(15);
        return view('academic.term-promotions.index', compact('promotions'));
    }

    public function show(TermPromotion $termPromotion)
    {
        $termPromotion->load(['student', 'currentTerm', 'promotedToTerm', 'processedBy']);
        return view('academic.term-promotions.show', compact('termPromotion'));
    }

    public function generate(Request $request)
    {
        $termId = $request->get('term_id');
        $cgpaThreshold = $request->get('cgpa_threshold', 2.0);
        $attendanceThreshold = $request->get('attendance_threshold', 75);

        $students = Student::where('current_term_id', $termId)->get();
        $count = 0;

        foreach ($students as $student) {
            TermPromotion::create([
                'student_id'              => $student->id,
                'current_term_id'         => $termId,
                'promoted_to_term_id'     => (int)$termId + 1,
                'cgpa'                    => 0,
                'attendance_percentage'   => 0,
                'meets_academic_criteria' => false,
                'meets_attendance_criteria' => false,
                'status'                  => 'pending',
            ]);
            $count++;
        }

        return redirect()->route('academic.term-promotions.index')
            ->with('success', "$count promotions generated successfully");
    }

    public function approve(TermPromotion $termPromotion)
    {
        if (!$termPromotion->canPromote()) {
            return back()->with('error', 'Student does not meet promotion criteria');
        }

        $termPromotion->approve();

        return back()->with('success', 'Promotion approved successfully');
    }

    public function reject(Request $request, TermPromotion $termPromotion)
    {
        $validated = $request->validate(['remarks' => 'required|string|max:500']);

        $termPromotion->reject($validated['remarks']);

        return back()->with('success', 'Promotion rejected successfully');
    }

    public function bulkApprove(Request $request)
    {
        $promotionIds = $request->get('promotion_ids', []);

        TermPromotion::whereIn('id', $promotionIds)->each(function (TermPromotion $tp) {
            if ($tp->canPromote()) {
                $tp->approve();
            }
        });

        return back()->with('success', 'Promotions processed successfully');
    }
}
