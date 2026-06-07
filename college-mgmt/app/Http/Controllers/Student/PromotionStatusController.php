<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Student, TermPromotion};
use Illuminate\Support\Facades\Auth;

class PromotionStatusController extends Controller {
    public function index() {
        $student = Student::where('user_id', Auth::id())->firstOrFail();
        $student->load(['currentTerm', 'program', 'batch']);

        $promotions = TermPromotion::where('student_id', $student->id)
            ->with(['currentTerm', 'promotedToTerm', 'processedBy'])
            ->orderByDesc('id')
            ->get();

        $latest = $promotions->first();

        return view('student.promotion.index', compact('student', 'promotions', 'latest'));
    }
}
