<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route(auth()->user()?->hasRole('admin') ? 'admin.dashboard' : (auth()->user()?->hasRole('teacher') ? 'teacher.dashboard' : 'student.dashboard'), absolute: false))
                    : view('auth.verify-email');
    }
}
