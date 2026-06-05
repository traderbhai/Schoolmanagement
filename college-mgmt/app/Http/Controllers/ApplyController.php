<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ApplyController extends Controller
{
    public function index()
    {
        $programs = Program::where('is_active', true)->get();
        return view('apply.index', compact('programs'));
    }

    public function show(Program $program)
    {
        return view('apply.register', compact('program'));
    }

    public function register(Request $request, Program $program)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|min:8|confirmed',
        ]);

        DB::transaction(function () use ($validated, $program, &$applicant) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            Role::firstOrCreate(['name' => 'applicant']);
            $user->assignRole('applicant');

            $applicant = Applicant::create([
                'user_id'    => $user->id,
                'program_id' => $program->id,
                'status'     => 'draft',
                'personal_data' => ['phone' => $validated['phone']],
            ]);
        });

        Auth::login(User::where('email', $validated['email'])->first());

        return redirect()->route('applicant.dashboard')
            ->with('success', 'Welcome! Your application has been created. Please complete all sections.');
    }
}
