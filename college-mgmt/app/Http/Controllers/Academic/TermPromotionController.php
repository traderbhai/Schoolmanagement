<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\TermPromotion;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TermPromotionController extends Controller
{
    public function index()
    {
        $promotions = TermPromotion::with(['student', 'currentTerm', 'promotedToTerm'])->paginate(15);
        return view('academic.term-promotions.index', compact('promotions'));
    }

    public function show(TermPromotion $promotion)
    {
        $promotion->load(['student', 'currentTerm', 'promotedToTerm', 'processedBy']);
        return view('academic.term-promotions.show', compact('promotion'));
    }

    public function generate(Request $request)
    {
        $term = $request->get('term_id');
        $cgpaThreshold = $request->get('cgpa_threshold', 2.0);
        $attendanceThreshold = $request->get('attendance_threshold', 75);

        $students = Student::where('current_term_id', $term)
            ->with(['currentTerm', 'enrollments.subject'])
            ->get();

        $promotionsGenerated = 0;

        foreach ($students as $student) {
            $cgpa = $student->calculateCGPA();
            $attendance = $student->calculateAttendancePercentage();

            $promotion = TermPromotion::create([
                'student_id' => $student->id,
                'current_term_id' => $term,
                'promoted_to_term_id' => (int)$term + 1,
                'cgpa' => $cgpa,
                'attendance_percentage' => $attendance,
                'meets_academic_criteria' => $cgpa >= $cgpaThreshold,
                'meets_attendance_criteria' => $attendance >= $attendanceThreshold,
                'status' => 'pending',
            ]);

            $promotionsGenerated++;
        }

        return redirect()->route('academic.term-promotions.index')
            ->with('success', "$promotionsGenerated promotions generated successfully");
    }

    public function approve(TermPromotion $promotion)
    {
        if (!$promotion->canPromote()) {
            return back()->with('error', 'Student does not meet promotion criteria');
        }

        $promotion->approve();

        return back()->with('success', 'Promotion approved successfully');
    }

    public function reject(Request $request, TermPromotion $promotion)
    {
        $validated = $request->validate(['remarks' => 'required|string|max:500']);

        $promotion->reject($validated['remarks']);

        return back()->with('success', 'Promotion rejected successfully');
    }

    public function bulkApprove(Request $request)
    {
        $promotionIds = $request->get('promotion_ids', []);

        TermPromotion::whereIn('id', $promotionIds)->each(function($promotion) {
            if ($promotion->canPromote()) {
                $promotion->approve();
            }
        });

        return back()->with('success', 'Promotions processed successfully');
    }
}
