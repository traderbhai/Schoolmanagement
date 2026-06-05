<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Student, Teacher, Notice};
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $r)
    {
        $q = $r->q;
        if (!$q || strlen($q) < 2) {
            return view('admin.search', ['results' => [], 'query' => $q, 'students' => collect(), 'teachers' => collect(), 'notices' => collect()]);
        }

        $students = Student::with('user')->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$q}%"))
            ->orWhere('enrollment_number', 'like', "%{$q}%")
            ->limit(5)->get();

        $teachers = Teacher::with('user')->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$q}%"))
            ->limit(5)->get();

        $notices = Notice::where('title', 'like', "%{$q}%")->limit(5)->get();

        return view('admin.search', compact('students', 'teachers', 'notices', 'q'));
    }
}
