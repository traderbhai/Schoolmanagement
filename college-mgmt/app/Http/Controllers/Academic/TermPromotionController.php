<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Term;
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
        $request->validate([
            'term_id'              => 'required|exists:terms,id',
            'cgpa_threshold'       => 'nullable|numeric|min:0|max:10',
            'attendance_threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        $termId              = $request->term_id;
        $cgpaThreshold       = $request->get('cgpa_threshold', 2.0);
        $attendanceThreshold = $request->get('attendance_threshold', 75);

        $currentTerm = Term::findOrFail($termId);

        // Find the next term in the same batch (next term_number)
        $nextTerm = Term::where('batch_id', $currentTerm->batch_id)
            ->where('term_number', '>', $currentTerm->term_number)
            ->orderBy('term_number')
            ->first();

        if (!$nextTerm) {
            return back()->with('error', 'No next term found for this batch. This may be the final term.');
        }

        $students = Student::where('current_term_id', $termId)->get();
        $count = 0;

        foreach ($students as $student) {
            // Skip if already generated for this student + term
            $exists = TermPromotion::where('student_id', $student->id)
                ->where('current_term_id', $termId)->exists();
            if ($exists) continue;

            $cgpa       = $student->calculateCGPA();
            $attendance = $student->calculateAttendancePercentage();

            TermPromotion::create([
                'student_id'                => $student->id,
                'current_term_id'           => $termId,
                'promoted_to_term_id'       => $nextTerm->id,
                'cgpa'                      => $cgpa,
                'attendance_percentage'     => $attendance,
                'meets_academic_criteria'   => $cgpa >= $cgpaThreshold,
                'meets_attendance_criteria' => $attendance >= $attendanceThreshold,
                'status'                    => 'pending',
            ]);
            $count++;
        }

        if ($count === 0) {
            return back()->with('error', 'No new promotions generated. Records may already exist for all students in this term.');
        }

        return redirect()->route('academic.term-promotions.index')
            ->with('success', "{$count} promotion records generated for term '{$currentTerm->name}' → '{$nextTerm->name}'.");
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
