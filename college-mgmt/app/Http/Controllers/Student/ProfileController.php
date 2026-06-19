<?php
namespace App\Http\Controllers\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller {
    public function index() {
        $student = auth()->user()->student;
        if (!$student) return redirect()->route('student.dashboard');
        $student->load(['user','department','program','batch','mentor']);
        return view('student.profile', compact('student'));
    }

    public function update(Request $request) {
        $student = auth()->user()->student;
        $user = auth()->user();

        if (! $student || $student->status !== 'active') {
            return back()->with('error', 'Profile updates are locked because this student profile is not active.');
        }

        $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'guardian_name'   => 'nullable|string|max:255',
            'guardian_phone'  => 'nullable|string|max:20',
            'password'        => 'nullable|string|min:8|confirmed',
            'photo'           => 'nullable|image|max:2048',
        ]);

        $user->update(['name' => $request->name]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $updateData = [
            'phone'          => $request->phone,
            'address'        => $request->address,
            'guardian_name'  => $request->guardian_name,
            'guardian_phone' => $request->guardian_phone,
        ];

        if ($request->hasFile('photo')) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('student-photos', 'public');
        }

        $student->update(array_filter($updateData, fn($v) => !is_null($v)));

        return back()->with('success', 'Profile updated successfully.');
    }
}
