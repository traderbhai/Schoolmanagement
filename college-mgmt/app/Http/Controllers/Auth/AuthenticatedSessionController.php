<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        \App\Models\ActivityLog::record('login', 'User logged in');

        $user = $request->user();
        if ($user->hasRole('admin'))             return redirect()->route('admin.dashboard');
        if ($user->hasRole('dean_academics'))    return redirect()->route('dean.dashboard');
        if ($user->hasRole('program_chair'))     return redirect()->route('chair.dashboard');
        if ($user->hasRole('hod'))               return redirect()->route('chair.dashboard');
        if ($user->hasRole('exam_cell'))         return redirect()->route('exam-cell.dashboard');
        if ($user->hasRole('accounts_officer'))  return redirect()->route('accounts.dashboard');
        if ($user->hasRole('admission_head'))    return redirect()->route('admission.dashboard');
        if ($user->hasRole('admission_officer')) return redirect()->route('admission.dashboard');
        if ($user->hasRole('teacher'))           return redirect()->route('teacher.dashboard');
        if ($user->hasRole('parent'))            return redirect()->route('parent.dashboard');
        if ($user->hasRole('applicant'))         return redirect()->route('applicant.dashboard');
        return redirect()->route('student.dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        \App\Models\ActivityLog::record('logout', 'User logged out');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
