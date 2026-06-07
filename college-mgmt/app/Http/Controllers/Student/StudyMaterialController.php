<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\StudyMaterial;
use Illuminate\Support\Facades\Auth;

class StudyMaterialController extends Controller
{
    public function index(Subject $subject)
    {
        $student = Auth::user()->student;
        abort_unless($student, 403);

        $materials = StudyMaterial::where('subject_id', $subject->id)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('type');

        return view('student.materials.index', compact('subject', 'materials'));
    }
}
