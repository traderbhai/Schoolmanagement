<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');
        $student->load(['user', 'department', 'course']);
        return view('student.profile', compact('student'));
    }

    public function update(Request $request)
    {
        $student = auth()->user()->student;
        $user = auth()->user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:500',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->update(['name' => $request->name]);
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }
        // Store phone/address if columns exist on students table
        // (they may not — store on user model or skip gracefully)

        return back()->with('success', 'Profile updated successfully.');
    }
}
