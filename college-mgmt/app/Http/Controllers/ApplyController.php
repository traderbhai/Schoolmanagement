<?php

namespace App\Http\Controllers;

use App\Models\ApplicationWindow;
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
        $applicationWindows = $this->availableWindowQuery()
            ->with(['program', 'batch'])
            ->orderBy('opens_at')
            ->get();

        return view('apply.index', compact('applicationWindows'));
    }

    public function show(Program $program)
    {
        if (! $program->is_active) {
            return redirect()->route('apply')->with('error', 'Applications for this program are not currently open.');
        }

        $window = $this->availableWindowQuery($program)
            ->orderBy('opens_at')
            ->first();

        if (!$window) {
            return redirect()->route('apply')->with('error', 'Applications for this program are not currently open.');
        }

        return view('apply.register', compact('program', 'window'));
    }

    public function register(Request $request, Program $program)
    {
        if (! $program->is_active) {
            return redirect()->route('apply')->with('error', 'Applications for this program are not currently open.');
        }

        $window = $this->availableWindowQuery($program)
            ->orderBy('opens_at')
            ->first();

        if (!$window) {
            $message = $this->hasOpenFullWindow($program)
                ? 'This program has reached its application capacity.'
                : 'Applications for this program are not currently open.';

            return redirect()->route('apply')->with('error', $message);
        }

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'required|string|max:20',
            'password' => 'required|min:8|confirmed',
        ]);

        DB::transaction(function () use ($validated, $program, $window, &$applicant, &$user) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            Role::firstOrCreate(['name' => 'applicant', 'guard_name' => 'web']);
            $user->assignRole('applicant');

            $applicant = Applicant::create([
                'user_id'    => $user->id,
                'program_id' => $program->id,
                'batch_id'   => $window->batch_id,
                'status'     => 'draft',
                'personal_data' => [
                    'name'  => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ],
            ]);

            $window->increment('current_applications');
        });

        Auth::login($user);

        return redirect()->route('applicant.dashboard')
            ->with('success', 'Welcome! Your application has been created. Please complete all sections.');
    }

    private function availableWindowQuery(?Program $program = null)
    {
        return ApplicationWindow::query()
            ->when($program, fn ($query) => $query->where('program_id', $program->id))
            ->where('is_active', true)
            ->whereHas('program', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) {
                $query->whereNull('batch_id')
                    ->orWhereHas('batch', fn ($batchQuery) => $batchQuery->whereColumn('batches.program_id', 'application_windows.program_id'));
            })
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->where(function ($query) {
                $query->whereNull('capacity_limit')
                    ->orWhereColumn('current_applications', '<', 'capacity_limit');
            });
    }

    private function hasOpenFullWindow(Program $program): bool
    {
        return ApplicationWindow::where('program_id', $program->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('batch_id')
                    ->orWhereHas('batch', fn ($batchQuery) => $batchQuery->whereColumn('batches.program_id', 'application_windows.program_id'));
            })
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>', now())
            ->whereNotNull('capacity_limit')
            ->whereColumn('current_applications', '>=', 'capacity_limit')
            ->exists();
    }
}
