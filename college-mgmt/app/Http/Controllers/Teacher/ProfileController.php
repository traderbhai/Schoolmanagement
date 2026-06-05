<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show()
    {
        $teacher = auth()->user()->teacher;
        $teacher->load('department', 'user');
        return view('teacher.profile', compact('teacher'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'designation'    => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:20',
            'specialization' => 'nullable|string|max:200',
            'qualification'  => 'nullable|string|max:200',
        ]);

        $teacher = auth()->user()->teacher;
        $teacher->update($request->only(['designation', 'phone', 'specialization', 'qualification']));

        return back()->with('success', 'Profile updated successfully.');
    }
}
